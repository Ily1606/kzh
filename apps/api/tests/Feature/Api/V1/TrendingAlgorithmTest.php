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

    public function test_trend_05_plugins_with_no_recent_activity_are_ignored(): void
    {
        config()->set('plugins.trending.days_limit', 10);

        $plugin1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subDays(1)]);
        $plugin2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subDays(15)]);

        // Manually track a view for plugin 1
        \App\Services\TrendingTracker::trackView($plugin1->id);

        $response = $this->getJson('/api/v1/plugins/trending');
        
        // Plugin 2 should be excluded because it has no recent activity
        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plugin1->id);
    }

    public function test_trend_06_to_08_score_calculation_ranks_purely_by_recent_interactions(): void
    {
        $p1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(2)]);
        $p2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subDays(20)]);
        
        \App\Services\TrendingTracker::trackView($p1->id);
        
        // P2 gets a lot of recent activity despite being old
        for ($i=0; $i<10; $i++) {
            \App\Services\TrendingTracker::trackStar($p2->id);
        }

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $p2->id) // Old but highly active
            ->assertJsonPath('data.1.id', $p1->id); 
    }

    public function test_trend_09_to_11_stars_comments_views_affect_score(): void
    {
        // Base plugin
        $p1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5)]);
        \App\Services\TrendingTracker::trackView($p1->id);

        // View plugin
        $p2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5)]);
        for($i=0; $i<10; $i++) \App\Services\TrendingTracker::trackView($p2->id);

        // Comment plugin
        $p3 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5)]);
        for($i=0; $i<10; $i++) \App\Services\TrendingTracker::trackComment($p3->id);

        // Star plugin (highest weight)
        $p4 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5)]);
        for($i=0; $i<10; $i++) \App\Services\TrendingTracker::trackStar($p4->id);

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $p4->id) // Stars have highest weight (10)
            ->assertJsonPath('data.1.id', $p3->id) // Comments have medium weight (5)
            ->assertJsonPath('data.2.id', $p2->id) // Views have lowest weight (1)
            ->assertJsonPath('data.3.id', $p1->id); // Lowest
    }

    public function test_trend_12_age_does_not_affect_score_anymore(): void
    {
        $newPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(1)]);
        $oldPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subYears(2)]);

        \App\Services\TrendingTracker::trackStar($newPlugin->id);
        \App\Services\TrendingTracker::trackStar($oldPlugin->id);
        \App\Services\TrendingTracker::trackStar($oldPlugin->id); // Old plugin has 2 stars

        Redis::del(config('plugins.trending.keys.zset')); // force refresh
        
        $response = $this->getJson('/api/v1/plugins/trending');
        
        // Old plugin wins purely based on having more stars in the time window
        $response->assertJsonPath('data.0.id', $oldPlugin->id);
    }

    public function test_trend_13_age_offset_prevents_division_by_zero(): void
    {
        // Age is exactly 0
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // If division by zero occurred, this would throw an exception 500 error
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_trend_14_per_page_parameter(): void
    {
        Plugin::factory()->count(20)->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        $response = $this->getJson('/api/v1/plugins/trending?per_page=5');
        $response->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_trend_15_sort_score_descending(): void
    {
        $p1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        $p2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        $p3 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        
        for($i=0; $i<10; $i++) \App\Services\TrendingTracker::trackView($p1->id);
        for($i=0; $i<100; $i++) \App\Services\TrendingTracker::trackView($p2->id);
        for($i=0; $i<50; $i++) \App\Services\TrendingTracker::trackView($p3->id);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $p2->id) // 100 views
            ->assertJsonPath('data.1.id', $p3->id) // 50 views
            ->assertJsonPath('data.2.id', $p1->id); // 10 views
    }

    public function test_trend_16_to_18_cache_behavior(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        \App\Services\TrendingTracker::trackView($plugin->id);

        // Cache miss
        $this->assertEquals(0, Redis::zcard(config('plugins.trending.keys.zset')));
        $this->getJson('/api/v1/plugins/trending')->assertOk();

        // Cache hit
        $this->assertEquals(1, Redis::zcard(config('plugins.trending.keys.zset')));

        // If we create a new plugin, it shouldn't appear because we hit cache
        $plugin2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        for($i=0; $i<100; $i++) \App\Services\TrendingTracker::trackView($plugin2->id);

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
        \App\Services\TrendingTracker::trackView($plugin->id);

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
        \App\Services\TrendingTracker::trackView($plugin->id);

        // First call caches it
        $this->getJson('/api/v1/plugins/trending');

        // Verify it is in ZSET and Hash
        $this->assertEquals(1, Redis::zcard(config('plugins.trending.keys.zset')));
        $this->assertNotNull(Redis::hget(config('plugins.trending.keys.objects'), $plugin->id));
    }

    public function test_trend_21_rejected_plugin_filtered_from_cache(): void
    {
        $plugin1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        $plugin2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        
        for($i=0; $i<10; $i++) \App\Services\TrendingTracker::trackView($plugin1->id);
        for($i=0; $i<5; $i++) \App\Services\TrendingTracker::trackView($plugin2->id);

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
