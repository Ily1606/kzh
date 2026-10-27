<?php

namespace Tests\Feature\Jobs;

use App\Enums\PluginStatus;
use App\Jobs\CascadeDeletePluginData;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\PluginReport;
use App\Models\Star;
use App\Models\User;
use App\Services\StarService;
use App\Services\TrendingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class CascadeDeletePluginDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_job_handles_non_existent_plugin_gracefully(): void
    {
        $nonExistentId = (string) Str::uuid();

        $job = new CascadeDeletePluginData($nonExistentId);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_job_does_not_delete_data_if_plugin_is_not_trashed(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $commenter->id,
        ]);

        $star = Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $commenter->id,
        ]);

        $job = new CascadeDeletePluginData($plugin);
        $job->handle();

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $commenter->id,
            'deleted_at' => null,
        ]);
    }

    public function test_job_cascades_soft_delete_to_comments_stars_and_reports(): void
    {
        $owner = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user1->id,
        ]);

        $report = CommentReport::create([
            'plugin_id' => $plugin->id,
            'comment_id' => $comment->id,
            'user_id' => $user2->id,
            'reason' => 'Inappropriate content',
        ]);

        $pluginReport = PluginReport::create([
            'plugin_id' => $plugin->id,
            'user_id' => $user2->id,
            'reason' => 'Plugin policy violation',
        ]);

        Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $user2->id,
        ]);

        // Trashed
        $plugin->delete();

        $job = new CascadeDeletePluginData($plugin);
        $job->handle();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
        $this->assertSoftDeleted('comment_reports', ['id' => $report->id]);
        $this->assertSoftDeleted('plugin_reports', ['id' => $pluginReport->id]);
        $this->assertSoftDeleted('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $user2->id,
        ]);
    }

    public function test_job_cleans_up_redis_trending_and_daily_stats(): void
    {
        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');
        $processingKey = $bufferKey.'_processing';
        $today = now()->format('Y-m-d');
        $activeKey = TrendingTracker::activeKey($today);
        $viewsStatKey = TrendingTracker::statKey('views', $today, $plugin->id);

        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));
        Redis::hset($bufferKey, $plugin->id, 5);
        Redis::hset($processingKey, $plugin->id, 2);
        Redis::sadd($activeKey, $plugin->id);
        Redis::set($viewsStatKey, 8);

        $plugin->delete();

        $job = new CascadeDeletePluginData($plugin);
        $job->handle();

        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($processingKey, $plugin->id));
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
        $this->assertNull(Redis::get($viewsStatKey));
    }

    public function test_deleting_plugin_dispatches_cascade_delete_job(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $plugin->delete();

        Queue::assertPushed(CascadeDeletePluginData::class, function ($job) use ($plugin) {
            return $job->pluginId === $plugin->id;
        });
    }

    public function test_child_comments_and_nested_replies_are_cascade_soft_deleted(): void
    {
        $owner = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        // 1. Root comment
        $root = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $userA->id,
            'parent_comment_id' => null,
        ]);

        // 2. Child reply
        $child = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $userB->id,
            'parent_comment_id' => $root->id,
        ]);

        // 3. Sub-reply
        $subChild = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $userA->id,
            'parent_comment_id' => $child->id,
        ]);

        $this->assertEquals(3, Comment::where('plugin_id', $plugin->id)->count());

        $plugin->delete();

        $job = new CascadeDeletePluginData($plugin);
        $job->handle();

        $this->assertTrue($root->fresh()->trashed());
        $this->assertTrue($child->fresh()->trashed());
        $this->assertTrue($subChild->fresh()->trashed());
        $this->assertEquals(0, Comment::where('plugin_id', $plugin->id)->count());
    }

    public function test_cleanup_clears_star_timeline_cache(): void
    {
        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $timelineKey = StarService::timelineCacheKey($plugin->id);
        Cache::put($timelineKey, ['timeline' => 'data_1'], 600);

        $plugin->delete();

        $job = new CascadeDeletePluginData($plugin);
        $job->handle();

        $this->assertNull(Cache::get($timelineKey));
    }

    public function test_cleanup_respects_custom_configured_redis_keys_and_prefixes(): void
    {
        config([
            'plugins.trending.keys.zset' => 'custom:plugins:zset',
            'plugins.trending.keys.objects' => 'custom:plugins:objects',
            'plugins.trending.keys.active_prefix' => 'custom:active_plugins',
            'plugins.trending.keys.stats_prefix' => 'custom:stats',
            'plugins.views_buffer_key' => 'custom:views_buffer',
        ]);

        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $today = now()->format('Y-m-d');
        $activeKey = TrendingTracker::activeKey($today);
        $statKey = TrendingTracker::statKey('views', $today, $plugin->id);

        $this->assertEquals("custom:active_plugins:{$today}", $activeKey);
        $this->assertEquals("custom:stats:views:{$today}:{$plugin->id}", $statKey);

        Redis::zadd('custom:plugins:zset', 50, $plugin->id);
        Redis::hset('custom:plugins:objects', $plugin->id, 'serialized_plugin');
        Redis::hset('custom:views_buffer', $plugin->id, 10);
        Redis::sadd($activeKey, $plugin->id);
        Redis::set($statKey, 20);

        $plugin->delete();

        $job = new CascadeDeletePluginData($plugin);
        $job->handle();

        $this->assertFalse((bool) Redis::zscore('custom:plugins:zset', $plugin->id));
        $this->assertFalse((bool) Redis::hexists('custom:plugins:objects', $plugin->id));
        $this->assertFalse((bool) Redis::hexists('custom:views_buffer', $plugin->id));
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
        $this->assertNull(Redis::get($statKey));
    }

    public function test_job_processes_large_number_of_comments_and_stars_in_chunks(): void
    {
        config(['plugins.cascade_delete.chunk_size' => 2]);

        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        // Create 5 comments and 5 stars across multiple users
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create();
            Comment::factory()->create([
                'plugin_id' => $plugin->id,
                'author_id' => $user->id,
            ]);
            Star::create([
                'plugin_id' => $plugin->id,
                'user_id' => $user->id,
            ]);
        }

        $this->assertEquals(5, Comment::where('plugin_id', $plugin->id)->count());
        $this->assertEquals(5, Star::where('plugin_id', $plugin->id)->count());

        $plugin->delete();

        $job = new CascadeDeletePluginData($plugin);
        $job->handle();

        $this->assertEquals(0, Comment::where('plugin_id', $plugin->id)->count());
        $this->assertEquals(0, Star::where('plugin_id', $plugin->id)->count());
        $this->assertEquals(5, Comment::withTrashed()->where('plugin_id', $plugin->id)->count());
        $this->assertEquals(5, Star::withTrashed()->where('plugin_id', $plugin->id)->count());
    }

    public function test_preserves_other_plugins_in_database_and_redis(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $user = User::factory()->create();

        $pluginA = Plugin::factory()->create([
            'user_id' => $ownerA->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $pluginB = Plugin::factory()->create([
            'user_id' => $ownerB->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Comment::factory()->create(['plugin_id' => $pluginA->id, 'author_id' => $user->id]);
        Comment::factory()->create(['plugin_id' => $pluginB->id, 'author_id' => $user->id]);

        Star::create(['plugin_id' => $pluginA->id, 'user_id' => $user->id]);
        Star::create(['plugin_id' => $pluginB->id, 'user_id' => $user->id]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        Redis::zadd($zsetKey, 100, $pluginA->id);
        Redis::zadd($zsetKey, 200, $pluginB->id);
        Redis::hset($hashKey, $pluginA->id, serialize($pluginA));
        Redis::hset($hashKey, $pluginB->id, serialize($pluginB));

        // Delete Plugin A only
        $pluginA->delete();

        $job = new CascadeDeletePluginData($pluginA);
        $job->handle();

        // Plugin A is cleaned up
        $this->assertTrue($pluginA->fresh()->trashed());
        $this->assertEquals(0, Comment::where('plugin_id', $pluginA->id)->count());
        $this->assertEquals(0, Star::where('plugin_id', $pluginA->id)->count());
        $this->assertFalse((bool) Redis::zscore($zsetKey, $pluginA->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $pluginA->id));

        // Plugin B is completely untouched
        $this->assertFalse($pluginB->fresh()->trashed());
        $this->assertEquals(1, Comment::where('plugin_id', $pluginB->id)->count());
        $this->assertEquals(1, Star::where('plugin_id', $pluginB->id)->count());
        $this->assertEquals(200, (int) Redis::zscore($zsetKey, $pluginB->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $pluginB->id));
    }

    public function test_job_tolerates_null_or_zero_chunk_size(): void
    {
        foreach ([null, 0, -1] as $invalidChunkSize) {
            config(['plugins.cascade_delete.chunk_size' => $invalidChunkSize]);

            $owner = User::factory()->create();
            $user = User::factory()->create();
            $plugin = Plugin::factory()->create([
                'user_id' => $owner->id,
                'status' => PluginStatus::Approved,
                'approved_at' => now(),
            ]);

            Comment::factory()->create(['plugin_id' => $plugin->id, 'author_id' => $user->id]);
            Star::create(['plugin_id' => $plugin->id, 'user_id' => $user->id]);

            $plugin->delete();

            $job = new CascadeDeletePluginData($plugin);
            $job->handle();

            $this->assertEquals(0, Comment::where('plugin_id', $plugin->id)->count());
            $this->assertEquals(0, Star::where('plugin_id', $plugin->id)->count());
        }
    }
}
