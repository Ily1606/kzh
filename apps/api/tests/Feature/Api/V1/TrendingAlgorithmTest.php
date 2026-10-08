<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TrendingAlgorithmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::flushall();
    }

    /**
     * Star count is COUNT over `stars`, so a test that wants a plugin to
     * "have N stars" must create the rows — one distinct user per star.
     */
    private function starTimes(Plugin $plugin, int $count): void
    {
        User::factory()->count($count)->create()->each(function (User $stargazer) use ($plugin): void {
            DB::table('stars')->insert([
                'plugin_id' => $plugin->id,
                'user_id' => $stargazer->id,
            ]);
        });
    }

    public function test_trend_01_plugins_within_days_limit_are_returned(): void
    {
        config()->set('plugins.trending.days_limit', 30);

        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(10),
        ]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plugin->id);
    }

    public function test_trend_02_fallback_all_time_when_no_recent_plugins(): void
    {
        config()->set('plugins.trending.days_limit', 7);

        // Plugin older than 7 days
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(10),
        ]);

        // Returns fallback list
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plugin->id);
    }

    public function test_trend_03_pending_or_rejected_plugins_are_ignored(): void
    {
        Plugin::factory()->create(['status' => PluginStatus::Pending, 'approved_at' => now()]);
        Plugin::factory()->create(['status' => PluginStatus::Rejected, 'approved_at' => now()]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_trend_04_plugins_without_approved_at_are_ignored(): void
    {
        Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => null]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_trend_05_plugins_too_old_are_ignored_if_recent_plugins_exist(): void
    {
        config()->set('plugins.trending.days_limit', 10);

        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(1), // Within limit
        ]);

        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(15), // Too old
        ]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_trend_06_to_08_score_calculation_ranks_newer_plugins_higher_unless_interactions_are_massive(): void
    {
        // TREND-06: New, few interactions
        $newPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subHours(2),
            'view_count' => 10,
            'comment_count' => 1,
        ]);
        $this->starTimes($newPlugin, 1);

        // TREND-07: Old, many interactions
        $oldPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(20),
            'view_count' => 1000,
            'comment_count' => 50,
        ]);
        $this->starTimes($oldPlugin, 100);

        // TREND-08: New, massive interactions
        $superNewPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subHours(1),
            'view_count' => 500,
            'comment_count' => 20,
        ]);
        $this->starTimes($superNewPlugin, 50);

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $superNewPlugin->id) // #1: Super new and popular
            ->assertJsonPath('data.1.id', $newPlugin->id)      // #2: New but not popular (Gravity decays old plugin heavily)
            ->assertJsonPath('data.2.id', $oldPlugin->id);     // #3: Old but very popular
    }

    public function test_trend_09_to_11_stars_comments_views_affect_score(): void
    {
        // Base plugin
        $p1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5), 'view_count' => 0, 'comment_count' => 0]);
        // View plugin
        $p2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5), 'view_count' => 100, 'comment_count' => 0]);
        // Comment plugin
        $p3 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5), 'view_count' => 0, 'comment_count' => 100]);
        // Star plugin (highest weight)
        $p4 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5), 'view_count' => 0, 'comment_count' => 0]);
        $this->starTimes($p4, 100);

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $p4->id) // Stars have highest weight (10)
            ->assertJsonPath('data.1.id', $p3->id) // Comments have medium weight (5)
            ->assertJsonPath('data.2.id', $p2->id) // Views have lowest weight (1)
            ->assertJsonPath('data.3.id', $p1->id); // None
    }

    public function test_trend_12_gravity_affects_score(): void
    {
        $newPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(1), 'view_count' => 10]);
        $oldPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(24), 'view_count' => 1000]);

        // With gravity 0, time doesn't matter, old plugin should win due to high views
        config()->set('plugins.trending.gravity', 0);
        Redis::flushall();
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertJsonPath('data.0.id', $oldPlugin->id);

        // With extreme gravity 5.0, old plugin should lose heavily
        config()->set('plugins.trending.gravity', 5.0);
        Redis::flushall();
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertJsonPath('data.0.id', $newPlugin->id);
    }

    public function test_trend_13_age_offset_prevents_division_by_zero(): void
    {
        // Age is exactly 0
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // If division by zero occurred, this would throw an exception 500 error
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_trend_14_limit_parameter(): void
    {
        Plugin::factory()->count(20)->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        $response = $this->getJson('/api/v1/plugins/trending?limit=5');
        $response->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_trend_15_sort_score_descending(): void
    {
        $p1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 10]);
        $p2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 100]);
        $p3 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 50]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $p2->id) // 100 views
            ->assertJsonPath('data.1.id', $p3->id) // 50 views
            ->assertJsonPath('data.2.id', $p1->id); // 10 views
    }

    public function test_trend_16_to_18_cache_behavior(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // Cache miss
        $this->assertEquals(0, Redis::zcard(config('plugins.trending.keys.zset')));
        $this->getJson('/api/v1/plugins/trending')->assertOk();

        // Cache hit
        $this->assertEquals(1, Redis::zcard(config('plugins.trending.keys.zset')));

        // If we create a new plugin, it shouldn't appear because we hit cache
        $plugin2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 9999]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonCount(1, 'data') // Still 1
            ->assertJsonPath('data.0.id', $plugin->id);

        // Clear cache and verify
        Redis::del(config('plugins.trending.keys.zset'));
        Redis::del(config('plugins.trending.keys.objects'));
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $plugin2->id); // Plugin 2 is now first
    }

    public function test_trend_19_resource_format(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'title',
                        'license',
                        'source_link',
                        'user_id',
                        'status',
                        'star_count',
                        'comment_count',
                        'view_count',
                        'approved_at',
                    ],
                ],
            ]);
    }

    public function test_trend_20_cache_resolves_to_redis_structures(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // First call caches it
        $this->getJson('/api/v1/plugins/trending');

        // Verify it is in ZSET and Hash
        $this->assertEquals(1, Redis::zcard(config('plugins.trending.keys.zset')));
        $this->assertNotNull(Redis::hget(config('plugins.trending.keys.objects'), $plugin->id));
    }

    public function test_trend_21_rejected_plugin_filtered_from_cache(): void
    {
        $plugin1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 10]);
        $plugin2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 5]);

        // First call caches both plugins' IDs
        $response1 = $this->getJson('/api/v1/plugins/trending');
        $response1->assertOk()->assertJsonCount(2, 'data');

        // Reject plugin1
        $plugin1->status = PluginStatus::Rejected;
        $plugin1->save();

        // Second call hits the cache (which still has plugin1's ID), but it should be filtered out when loaded from DB
        $response2 = $this->getJson('/api/v1/plugins/trending');
        $response2->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plugin2->id);
    }
}
