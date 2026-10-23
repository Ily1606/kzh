<?php

namespace Tests\Feature\Jobs;

use App\Enums\PluginStatus;
use App\Jobs\CascadeDeletePluginData;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\Star;
use App\Models\User;
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
}
