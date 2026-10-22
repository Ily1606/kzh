<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use App\Services\TrendingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TrendingAlgorithmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::flushdb();
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

    /**
     * Comment count is COUNT over `comments` for the same reason, so the
     * weighted-comment cases seed rows rather than setting a column.
     * `view_count` is the one interaction still stored as a number, because no
     * table holds that fact — see `SyncPluginViews`.
     */
    private function commentTimes(Plugin $plugin, int $count): void
    {
        Comment::factory()->count($count)->create([
            'plugin_id' => $plugin->id,
        ]);
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
            ->assertJsonCount(1, 'data.plugins')
            ->assertJsonPath('data.plugins.0.id', $plugin->id);
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
            ->assertJsonCount(1, 'data.plugins')
            ->assertJsonPath('data.plugins.0.id', $plugin->id);
    }

    public function test_trend_03_pending_or_rejected_plugins_are_ignored(): void
    {
        Plugin::factory()->create(['status' => PluginStatus::Pending, 'approved_at' => now()]);
        Plugin::factory()->create(['status' => PluginStatus::Rejected, 'approved_at' => now()]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(0, 'data.plugins');
    }

    public function test_trend_04_plugins_without_approved_at_are_ignored(): void
    {
        Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => null]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(0, 'data.plugins');
    }

    public function test_trend_05_plugins_too_old_are_ignored_if_recent_plugins_exist(): void
    {
        config()->set('plugins.trending.days_limit', 10);

        $recent = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(1),
        ]);
        TrendingTracker::trackView($recent->id);

        $old = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(15),
        ]);
        Redis::sadd('trending:active_plugins:' . now()->subDays(15)->format('Y-m-d'), $old->id);
        Redis::set('trending:stats:views:' . now()->subDays(15)->format('Y-m-d') . ':' . $old->id, 10);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonCount(1, 'data.plugins')
            ->assertJsonPath('data.plugins.0.id', $recent->id);
    }

    public function test_trend_06_to_08_score_calculation_ranks_newer_plugins_higher_unless_interactions_are_massive(): void
    {
        config()->set('plugins.trending.days_limit', 7);

        // TREND-06: Recent with few interactions
        $newPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subHours(2),
        ]);
        TrendingTracker::trackStar($newPlugin->id, 1);
        TrendingTracker::trackComment($newPlugin->id, 1);

        // TREND-07: Old interactions outside the sliding window
        $oldPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(20),
        ]);
        Redis::sadd('trending:active_plugins:' . now()->subDays(20)->format('Y-m-d'), $oldPlugin->id);
        Redis::set('trending:stats:stars:' . now()->subDays(20)->format('Y-m-d') . ':' . $oldPlugin->id, 100);

        // TREND-08: Recent with massive interactions
        $superNewPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subHours(1),
        ]);
        TrendingTracker::trackStar($superNewPlugin->id, 50);
        TrendingTracker::trackComment($superNewPlugin->id, 20);

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonCount(2, 'data.plugins')
            ->assertJsonPath('data.plugins.0.id', $superNewPlugin->id) // #1: Massive recent interactions
            ->assertJsonPath('data.plugins.1.id', $newPlugin->id);      // #2: Recent interactions
    }

    public function test_trend_09_to_11_stars_comments_views_affect_score(): void
    {
        $today = now()->format('Y-m-d');

        // View plugin
        $p2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5)]);
        Redis::set("trending:stats:views:{$today}:{$p2->id}", 100);
        Redis::sadd("trending:active_plugins:{$today}", $p2->id);

        // Comment plugin
        $p3 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5)]);
        Redis::set("trending:stats:comments:{$today}:{$p3->id}", 100);
        Redis::sadd("trending:active_plugins:{$today}", $p3->id);

        // Star plugin (highest weight)
        $p4 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(5)]);
        Redis::set("trending:stats:stars:{$today}:{$p4->id}", 100);
        Redis::sadd("trending:active_plugins:{$today}", $p4->id);

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonPath('data.plugins.0.id', $p4->id) // Stars have highest weight (10)
            ->assertJsonPath('data.plugins.1.id', $p3->id) // Comments have medium weight (5)
            ->assertJsonPath('data.plugins.2.id', $p2->id); // Views have lowest weight (1)
    }

    /**
     * The trending queries build their own SQL rather than going through
     * `withCount()`, so they cannot inherit `Comment::scopeVisible()`. Without this
     * test a hand-written condition drifting from the Eloquent one would be silent:
     * the ranking would follow moderated comments the user cannot read, while the
     * list endpoint counted them out.
     */
    public function test_moderated_comments_do_not_affect_the_score(): void
    {
        $visible = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subHours(5),
        ]);
        $this->commentTimes($visible, 100);

        $moderated = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subHours(5),
        ]);
        Comment::factory()->count(100)->hidden()->create(['plugin_id' => $moderated->id]);

        Redis::flushdb();

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonPath('data.plugins.0.id', $visible->id)
            // Both numbers come from the same visible-only rule: 100 counted here,
            // and 0 for the plugin whose 100 rows are all moderated.
            ->assertJsonPath('data.plugins.0.comment_count', 100)
            ->assertJsonPath('data.plugins.1.comment_count', 0);
    }

    public function test_trend_12_gravity_affects_score(): void
    {
        $today = now()->format('Y-m-d');
        $viewPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        $starPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        Redis::set("trending:stats:views:{$today}:{$viewPlugin->id}", 100);
        Redis::sadd("trending:active_plugins:{$today}", $viewPlugin->id);

        Redis::set("trending:stats:stars:{$today}:{$starPlugin->id}", 15);
        Redis::sadd("trending:active_plugins:{$today}", $starPlugin->id);

        // With default weights: starPlugin wins (15 * 10 = 150 > 100 * 1 = 100)
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertJsonPath('data.plugins.0.id', $starPlugin->id);

        // Clear Redis cache and increase view weight to 2.0: viewPlugin wins (100 * 2 = 200 > 150)
        Redis::del(config('plugins.trending.keys.zset'), config('plugins.trending.keys.objects'));
        Cache::forget('plugins:trending:refresh_attempted');
        config()->set('plugins.trending.weights.view', 2.0);
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertJsonPath('data.plugins.0.id', $viewPlugin->id);
    }

    public function test_trend_13_age_offset_prevents_division_by_zero(): void
    {
        // Age is exactly 0
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // If division by zero occurred, this would throw an exception 500 error
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(1, 'data.plugins');
    }

    public function test_trend_14_limit_parameter(): void
    {
        Plugin::factory()->count(20)->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        $response = $this->getJson('/api/v1/plugins/trending?limit=5');
        $response->assertOk()->assertJsonCount(5, 'data.plugins');
    }

    public function test_trend_15_sort_score_descending(): void
    {
        $p1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 10]);
        $p2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 100]);
        $p3 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 50]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonPath('data.plugins.0.id', $p2->id) // 100 views
            ->assertJsonPath('data.plugins.1.id', $p3->id) // 50 views
            ->assertJsonPath('data.plugins.2.id', $p1->id); // 10 views
    }

    public function test_trend_16_to_18_cache_behavior(): void
    {
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // Cache miss
        $this->assertEquals(0, Redis::zcard($zsetKey));
        $this->getJson('/api/v1/plugins/trending')->assertOk();

        // Cache hit
        $this->assertGreaterThan(0, Redis::zcard($zsetKey));

        // If we create a new plugin, it shouldn't appear because we hit cache
        $plugin2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 9999]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonCount(1, 'data.plugins') // Still 1
            ->assertJsonPath('data.plugins.0.id', $plugin->id);

        // Clear cache and verify
        Redis::del($zsetKey, $hashKey);
        Cache::forget('plugins:trending:refresh_attempted');
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonCount(2, 'data.plugins')
            ->assertJsonPath('data.plugins.0.id', $plugin2->id); // Plugin 2 is now first
    }

    public function test_trend_19_resource_format(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'plugins' => [
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
                ],
            ]);
    }

    public function test_trend_20_cache_resolves_to_array_not_eloquent_collection(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // First call caches it
        $this->getJson('/api/v1/plugins/trending');

        $hashKey = config('plugins.trending.keys.objects');
        $zsetKey = config('plugins.trending.keys.zset');

        $this->assertGreaterThan(0, Redis::zcard($zsetKey));
        $cachedData = Redis::hget($hashKey, $plugin->id);
        $this->assertNotNull($cachedData);

        $unserialized = unserialize($cachedData);
        $this->assertInstanceOf(Plugin::class, $unserialized);
        $this->assertSame($plugin->id, $unserialized->id);
    }

    // -----------------------------------------------------------------------
    // is_star
    // -----------------------------------------------------------------------

    private function givenStarred(Plugin $plugin, User $user): void
    {
        DB::table('stars')->insert([
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_trend_21_marks_is_star_for_a_signed_in_user(): void
    {
        $user = User::factory()->create();

        $starred = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        $notStarred = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subMinute(),
        ]);

        $this->givenStarred($starred, $user);

        Sanctum::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonPath('data.plugins.0.id', $starred->id)
            ->assertJsonPath('data.plugins.0.is_star', true)
            ->assertJsonPath('data.plugins.1.id', $notStarred->id)
            ->assertJsonPath('data.plugins.1.is_star', false);
    }

    public function test_trend_22_guest_response_has_no_is_star(): void
    {
        Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        $this->getJson('/api/v1/plugins/trending')
            ->assertOk()
            ->assertJsonMissingPath('data.plugins.0.is_star');
    }

    public function test_trend_23_is_star_does_not_leak_through_the_shared_cache(): void
    {
        $user = User::factory()->create();

        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        $this->givenStarred($plugin, $user);

        // Warm the cache as a signed-in user. The flag is resolved for this
        // request only and must never be written into the cached value.
        Sanctum::actingAs($user, [], 'api');

        $this->getJson('/api/v1/plugins/trending')
            ->assertOk()
            ->assertJsonPath('data.plugins.0.is_star', true);

        $hashKey = config('plugins.trending.keys.objects');
        $cachedPlugin = unserialize(Redis::hget($hashKey, $plugin->id));
        $this->assertNull(
            $cachedPlugin->is_star,
            'The cached payload must stay viewer-independent.',
        );

        // The same cache entry now serves a guest. One entry serves every user,
        // so storing the flag inside it would hand this user's stars to the next.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/plugins/trending')
            ->assertOk()
            ->assertJsonPath('data.plugins.0.id', $plugin->id)
            ->assertJsonMissingPath('data.plugins.0.is_star');
    }

    public function test_trend_24_is_star_is_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        $this->givenStarred($plugin, $owner);

        Sanctum::actingAs($other, [], 'api');

        $this->getJson('/api/v1/plugins/trending')
            ->assertOk()
            ->assertJsonPath('data.plugins.0.is_star', false);
    }

    public function test_trend_25_trending_tracker_ttl_matches_configured_days_limit(): void
    {
        config()->set('plugins.trending.days_limit', 30);
        $this->assertSame(86400 * 31, TrendingTracker::ttl());

        config()->set('plugins.trending.days_limit', 7);
        $this->assertSame(86400 * 8, TrendingTracker::ttl());
    }

    public function test_trend_26_trending_tracker_sets_expected_redis_ttl(): void
    {
        config()->set('plugins.trending.days_limit', 14);
        $expectedTtl = TrendingTracker::ttl();
        $this->assertSame(86400 * 15, $expectedTtl);

        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        TrendingTracker::trackView($plugin->id);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:views:{$date}:{$plugin->id}";
        $activeKey = "trending:active_plugins:{$date}";

        $this->assertGreaterThan(0, Redis::ttl($statKey));
        $this->assertLessThanOrEqual($expectedTtl, Redis::ttl($statKey));
        $this->assertGreaterThanOrEqual($expectedTtl - 5, Redis::ttl($statKey));

        $this->assertGreaterThan(0, Redis::ttl($activeKey));
        $this->assertLessThanOrEqual($expectedTtl, Redis::ttl($activeKey));
        $this->assertGreaterThanOrEqual($expectedTtl - 5, Redis::ttl($activeKey));
    }

    public function test_trend_27_recover_trending_stats_command_uses_calculated_ttl(): void
    {
        config()->set('plugins.trending.days_limit', 30);
        $expectedTtl = TrendingTracker::ttl();

        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'created_at' => now(),
        ]);

        $this->artisan('plugins:recover-trending')
            ->assertSuccessful();

        $date = now()->format('Y-m-d');
        $commentKey = "trending:stats:comments:{$date}:{$plugin->id}";
        $activeKey = "trending:active_plugins:{$date}";

        $this->assertGreaterThan(0, Redis::ttl($commentKey));
        $this->assertLessThanOrEqual($expectedTtl, Redis::ttl($commentKey));
        $this->assertGreaterThanOrEqual($expectedTtl - 5, Redis::ttl($commentKey));

        $this->assertGreaterThan(0, Redis::ttl($activeKey));
        $this->assertLessThanOrEqual($expectedTtl, Redis::ttl($activeKey));
        $this->assertGreaterThanOrEqual($expectedTtl - 5, Redis::ttl($activeKey));
    }

    public function test_trend_28_empty_zset_throttles_refresh_attempt(): void
    {
        // When database is empty, first call attempts refresh and sets lock
        $response1 = $this->getJson('/api/v1/plugins/trending');
        $response1->assertOk()->assertJsonCount(0, 'data.plugins');

        $this->assertTrue(Cache::has('plugins:trending:refresh_attempted'));

        // Now create an approved plugin
        Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // A second immediate request will hit the throttle lock and won't call refresh-trending
        $response2 = $this->getJson('/api/v1/plugins/trending');
        $response2->assertOk()->assertJsonCount(0, 'data.plugins');

        // Once the lock expires / is cleared, refresh-trending runs again
        Cache::forget('plugins:trending:refresh_attempted');
        $response3 = $this->getJson('/api/v1/plugins/trending');
        $response3->assertOk()->assertJsonCount(1, 'data.plugins');
    }

    public function test_trend_29_recover_trending_stats_command_ignores_hidden_comments(): void
    {
        config()->set('plugins.trending.days_limit', 30);
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // 1 visible comment
        Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'hidden_at' => null,
            'created_at' => now(),
        ]);

        // 2 hidden comments
        Comment::factory()->count(2)->create([
            'plugin_id' => $plugin->id,
            'hidden_at' => now(),
            'created_at' => now(),
        ]);

        Redis::flushdb();

        $this->artisan('plugins:recover-trending')
            ->assertSuccessful();

        $date = now()->format('Y-m-d');
        $commentKey = "trending:stats:comments:{$date}:{$plugin->id}";

        // Only the 1 visible comment should be recovered
        $this->assertEquals(1, (int) Redis::get($commentKey));
    }

    public function test_trend_30_get_bulk_interaction_counts_chunks_correctly_when_over_100_plugins(): void
    {
        $pluginIds = [];
        for ($i = 0; $i < 150; $i++) {
            $pluginIds[] = "plugin-test-{$i}";
        }

        $today = now()->format('Y-m-d');

        // Seed interaction for plugin in chunk 1 (e.g. index 10)
        Redis::set("trending:stats:views:{$today}:plugin-test-10", 5);
        Redis::set("trending:stats:comments:{$today}:plugin-test-10", 2);

        // Seed interaction for plugin in chunk 2 (e.g. index 120)
        Redis::set("trending:stats:stars:{$today}:plugin-test-120", 7);

        $counts = TrendingTracker::getBulkInteractionCounts($pluginIds, 7);

        $this->assertCount(150, $counts);
        $this->assertSame(5, $counts['plugin-test-10']['views']);
        $this->assertSame(2, $counts['plugin-test-10']['comments']);
        $this->assertSame(0, $counts['plugin-test-10']['stars']);

        $this->assertSame(0, $counts['plugin-test-120']['views']);
        $this->assertSame(0, $counts['plugin-test-120']['comments']);
        $this->assertSame(7, $counts['plugin-test-120']['stars']);

        $this->assertSame(0, $counts['plugin-test-0']['views']);
    }

    public function test_trend_31_auto_recover_trending_when_redis_is_empty_and_enabled(): void
    {
        config()->set('plugins.trending.auto_recover', true);

        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(1),
        ]);

        $this->commentTimes($plugin, 5);

        // Simulate Redis restart / complete data loss
        Redis::flushdb();
        Cache::flush();

        // Active plugin keys in Redis should be completely empty
        $this->assertEmpty(TrendingTracker::getActivePluginIds(7));

        // When requesting trending, auto_recover should automatically restore stats from DB
        $response = $this->getJson('/api/v1/plugins/trending');

        $response->assertOk()
            ->assertJsonCount(1, 'data.plugins')
            ->assertJsonPath('data.plugins.0.id', $plugin->id)
            ->assertJsonPath('data.plugins.0.comment_count', 5);

        // Redis active plugin keys and stats should now be populated
        $this->assertNotEmpty(TrendingTracker::getActivePluginIds(7));
    }
}

