<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\Star;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PluginObserverTest extends TestCase
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

    public function test_deleting_plugin_removes_it_from_trending_redis_caches(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');
        $date = now()->format('Y-m-d');
        $activeKey = 'trending:active_plugins:' . $date;
        $statKey = "trending:stats:views:{$date}:{$plugin->id}";

        // Seed plugin into Redis trending caches
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));
        Redis::hset($bufferKey, $plugin->id, 5);
        Redis::sadd($activeKey, $plugin->id);
        Redis::set($statKey, 10);

        $this->assertEquals(100, Redis::zscore($zsetKey, $plugin->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertTrue((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertTrue((bool) Redis::sismember($activeKey, $plugin->id));
        $this->assertTrue((bool) Redis::exists($statKey));

        // Soft delete the plugin
        $plugin->delete();

        // Verify it was removed from Redis trending caches and stat keys were deleted
        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
        $this->assertFalse((bool) Redis::exists($statKey));
    }

    public function test_deleting_plugin_cascades_soft_delete_to_comments_and_stars(): void
    {
        $owner = User::factory()->create();
        $stargazer = User::factory()->create();
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

        $report = CommentReport::create([
            'plugin_id' => $plugin->id,
            'comment_id' => $comment->id,
            'user_id' => $stargazer->id,
            'reason' => 'Spam comment',
        ]);

        Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $stargazer->id,
        ]);

        // Soft delete plugin
        $plugin->delete();

        // Verify cascade soft delete
        $this->assertSoftDeleted('plugins', ['id' => $plugin->id]);
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
        $this->assertSoftDeleted('comment_reports', ['id' => $report->id]);
        $this->assertSoftDeleted('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $stargazer->id,
        ]);
    }

    public function test_force_deleting_plugin_removes_it_from_trending_redis_caches(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');
        $activeKey = 'trending:active_plugins:'.now()->format('Y-m-d');

        Redis::zadd($zsetKey, 50, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));
        Redis::hset($bufferKey, $plugin->id, 2);
        Redis::sadd($activeKey, $plugin->id);

        // Force delete
        $plugin->forceDelete();

        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
    }

    public function test_deleted_plugin_does_not_appear_in_trending_api_response(): void
    {
        $plugin1 = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
        $plugin2 = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        Redis::zadd($zsetKey, 100, $plugin1->id);
        Redis::hset($hashKey, $plugin1->id, serialize($plugin1));

        Redis::zadd($zsetKey, 80, $plugin2->id);
        Redis::hset($hashKey, $plugin2->id, serialize($plugin2));

        // Delete plugin1
        $plugin1->delete();

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonCount(1, 'data.plugins')
            ->assertJsonPath('data.plugins.0.id', $plugin2->id);
    }

    public function test_updating_cached_trending_plugin_refreshes_redis_hash_object(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
            'title' => 'Original Title',
        ]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Update plugin title
        $plugin->update(['title' => 'Brand New Updated Title']);

        // Assert Redis hash object was updated
        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals('Brand New Updated Title', $cachedPlugin->title);

        // Assert trending API returns updated title immediately
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonPath('data.plugins.0.title', 'Brand New Updated Title');
    }

    public function test_unapproving_cached_trending_plugin_removes_it_from_redis(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:views:{$date}:{$plugin->id}";

        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));
        Redis::set($statKey, 15);

        // Status changed away from Approved
        $plugin->status = PluginStatus::Pending;
        $plugin->save();

        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
        // Stat keys must be PRESERVED so that re-approving won't lose interaction history
        $this->assertTrue((bool) Redis::exists($statKey));
    }

    public function test_owner_patching_plugin_updates_redis_trending_cache(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
            'title' => 'Before Patch',
        ]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'After Patch',
        ]);

        $response->assertOk();

        // Check Redis hash is updated with the new title
        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals('After Patch', $cachedPlugin->title);
    }

    public function test_restoring_plugin_does_not_mark_it_active_without_interactions(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $activeKey = 'trending:active_plugins:'.now()->format('Y-m-d');

        // Delete plugin -> activeKey should not have it
        $plugin->delete();
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));

        // Restore plugin -> should NOT be added to activeKey without interaction
        $plugin->restore();
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
    }
}
