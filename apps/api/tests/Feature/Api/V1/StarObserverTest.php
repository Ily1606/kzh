<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\Star;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class StarObserverTest extends TestCase
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

    public function test_deleting_star_decrements_trending_stats_and_updates_cached_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $star = Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:stars:{$date}:{$plugin->id}";
        $hashKey = config('plugins.trending.keys.objects');
        $zsetKey = config('plugins.trending.keys.zset');

        // Set up initial trending interaction and cached plugin with star_count = 1
        Redis::set($statKey, 1);
        $plugin->star_count = 1;
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Soft delete the star
        $star->delete();

        // 1. Trending stats in Redis should be decremented to 0
        $this->assertEquals(0, (int) Redis::get($statKey));

        // 2. Cached plugin object in Redis should have star_count updated to 0
        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals(0, $cachedPlugin->star_count);

        // 3. Plugin repository star count SQL excludes soft-deleted star
        $this->assertEquals(0, $plugin->stars()->count());
    }

    public function test_restoring_star_increments_trending_stats_and_updates_cached_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $star = Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:stars:{$date}:{$plugin->id}";
        $hashKey = config('plugins.trending.keys.objects');
        $zsetKey = config('plugins.trending.keys.zset');

        // Initial setup
        Redis::set($statKey, 1);
        $plugin->star_count = 1;
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Soft delete the star
        $star->delete();
        $this->assertEquals(0, (int) Redis::get($statKey));

        // Restore the star
        $star->restore();
        $this->assertEquals(1, (int) Redis::get($statKey));

        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals(1, $cachedPlugin->star_count);
        $this->assertEquals(1, $plugin->stars()->count());
    }
}

