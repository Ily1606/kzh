<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
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
        $activeKey = 'trending:active_plugins:' . now()->format('Y-m-d');

        // Seed plugin into Redis trending caches
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));
        Redis::hset($bufferKey, $plugin->id, 5);
        Redis::sadd($activeKey, $plugin->id);

        $this->assertEquals(100, Redis::zscore($zsetKey, $plugin->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertTrue((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertTrue((bool) Redis::sismember($activeKey, $plugin->id));

        // Soft delete the plugin
        $plugin->delete();

        // Verify it was removed from Redis trending caches
        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
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
        $activeKey = 'trending:active_plugins:' . now()->format('Y-m-d');

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

        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Status changed away from Approved
        $plugin->status = PluginStatus::Pending;
        $plugin->save();

        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
    }

    public function test_owner_patching_plugin_updates_redis_trending_cache(): void
    {
        $user = \App\Models\User::factory()->create();
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

        \Laravel\Sanctum\Sanctum::actingAs($user);

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

        $activeKey = 'trending:active_plugins:' . now()->format('Y-m-d');

        // Delete plugin -> activeKey should not have it
        $plugin->delete();
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));

        // Restore plugin -> should NOT be added to activeKey without interaction
        $plugin->restore();
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
    }
}
