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

    public function test_deleting_old_star_decrements_its_creation_date_bucket_not_today(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $pastDate = now()->subDays(5);
        $star = Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);
        $star->created_at = $pastDate;
        $star->save();

        $pastDateStr = $pastDate->format('Y-m-d');
        $todayStr = now()->format('Y-m-d');

        $pastStatKey = "trending:stats:stars:{$pastDateStr}:{$plugin->id}";
        $todayStatKey = "trending:stats:stars:{$todayStr}:{$plugin->id}";

        Redis::set($pastStatKey, 1);
        Redis::set($todayStatKey, 5);

        // Act: Delete the star created 5 days ago
        $star->delete();

        // The past bucket should be decremented to 0
        $this->assertEquals(0, (int) Redis::get($pastStatKey));
        // Today's bucket must remain untouched at 5
        $this->assertEquals(5, (int) Redis::get($todayStatKey));
    }

    public function test_deleting_star_outside_trending_window_ignores_decrement_and_does_not_affect_today(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $expiredDate = now()->subDays(40);
        $star = Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);
        $star->created_at = $expiredDate;
        $star->save();

        $expiredDateStr = $expiredDate->format('Y-m-d');
        $todayStr = now()->format('Y-m-d');

        $expiredStatKey = "trending:stats:stars:{$expiredDateStr}:{$plugin->id}";
        $todayStatKey = "trending:stats:stars:{$todayStr}:{$plugin->id}";

        // The expired bucket does not exist in Redis
        Redis::del($expiredStatKey);
        Redis::set($todayStatKey, 4);

        // Act: Delete the 40-day-old star
        $star->delete();

        // Expired key should not be recreated
        $this->assertFalse((bool) Redis::exists($expiredStatKey));
        // Today's bucket must remain untouched at 4
        $this->assertEquals(4, (int) Redis::get($todayStatKey));
    }

    public function test_unstarring_via_star_service_decrements_creation_date_bucket_not_today(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $pastDate = now()->subDays(10);
        $pastDateStr = $pastDate->format('Y-m-d');
        $todayStr = now()->format('Y-m-d');

        // Insert star row directly with past created_at
        \Illuminate\Support\Facades\DB::table('stars')->insert([
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
            'created_at' => $pastDate,
            'updated_at' => $pastDate,
        ]);

        $pastStatKey = "trending:stats:stars:{$pastDateStr}:{$plugin->id}";
        $todayStatKey = "trending:stats:stars:{$todayStr}:{$plugin->id}";

        Redis::set($pastStatKey, 1);
        Redis::set($todayStatKey, 7);

        // Act: Unstar via StarService
        $service = app(\App\Services\StarService::class);
        $service->setStarred($user, $plugin->id, false, new \App\Support\RequestContext(null, null));

        // Past bucket decremented
        $this->assertEquals(0, (int) Redis::get($pastStatKey));
        // Today's bucket untouched
        $this->assertEquals(7, (int) Redis::get($todayStatKey));
    }
}

