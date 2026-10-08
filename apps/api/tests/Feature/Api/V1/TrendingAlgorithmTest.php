<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrendingAlgorithmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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

        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(1), // Within limit
        ]);

        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(15), // Too old
        ]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()->assertJsonCount(1, 'data.plugins');
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
            ->assertJsonCount(3, 'data.plugins')
            ->assertJsonPath('data.plugins.0.id', $superNewPlugin->id) // #1: Super new and popular
            ->assertJsonPath('data.plugins.1.id', $newPlugin->id)      // #2: New but not popular (Gravity decays old plugin heavily)
            ->assertJsonPath('data.plugins.2.id', $oldPlugin->id);     // #3: Old but very popular
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
            ->assertJsonPath('data.plugins.0.id', $p4->id) // Stars have highest weight (10)
            ->assertJsonPath('data.plugins.1.id', $p3->id) // Comments have medium weight (5)
            ->assertJsonPath('data.plugins.2.id', $p2->id) // Views have lowest weight (1)
            ->assertJsonPath('data.plugins.3.id', $p1->id); // None
    }

    public function test_trend_12_gravity_affects_score(): void
    {
        $newPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(1), 'view_count' => 10]);
        $oldPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()->subHours(24), 'view_count' => 1000]);

        // With gravity 0, time doesn't matter, old plugin should win due to high views
        config()->set('plugins.trending.gravity', 0);
        Cache::flush();
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertJsonPath('data.plugins.0.id', $oldPlugin->id);

        // With extreme gravity 5.0, old plugin should lose heavily
        config()->set('plugins.trending.gravity', 5.0);
        Cache::flush();
        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertJsonPath('data.plugins.0.id', $newPlugin->id);
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
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // Cache miss
        $this->assertFalse(Cache::has('plugins:trending:15'));
        $this->getJson('/api/v1/plugins/trending')->assertOk();

        // Cache hit
        $this->assertTrue(Cache::has('plugins:trending:15'));

        // If we create a new plugin, it shouldn't appear because we hit cache
        $plugin2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now(), 'view_count' => 9999]);

        $response = $this->getJson('/api/v1/plugins/trending');
        $response->assertOk()
            ->assertJsonCount(1, 'data.plugins') // Still 1
            ->assertJsonPath('data.plugins.0.id', $plugin->id);

        // Clear cache and verify
        Cache::flush();
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
        Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        // First call caches it
        $this->getJson('/api/v1/plugins/trending');

        // Retrieve from cache
        $cachedData = Cache::get('plugins:trending:15');

        // Verify it is an array
        $this->assertIsArray($cachedData);
        $this->assertIsArray($cachedData[0]);
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

        $this->assertArrayNotHasKey(
            'is_star',
            Cache::get('plugins:trending:15')[0],
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
}
