<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use App\Repositories\PluginRepository;
use App\Services\StarService;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class StarPluginTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function approvedPlugin(): Plugin
    {
        return Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
            'star_count' => 0,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function star(Plugin $plugin, array $payload = ['starred' => true]): TestResponse
    {
        return $this->postJson("/api/v1/plugins/{$plugin->id}/star", $payload);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function test_authenticated_user_can_star_a_plugin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $plugin = $this->approvedPlugin();

        $this->star($plugin)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('api.plugin_starred_successfully'))
            ->assertJsonPath('data.starred', true)
            ->assertJsonPath('data.star_count', 1);

        $this->assertDatabaseHas('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_unstar_a_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->star($plugin)->assertOk();

        $this->star($plugin, ['starred' => false])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('api.plugin_unstarred_successfully'))
            ->assertJsonPath('data.starred', false)
            ->assertJsonPath('data.star_count', 0);

        $this->assertDatabaseCount('stars', 0);
    }

    public function test_the_response_reports_the_count_for_the_second_user_to_star(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $this->star($plugin)->assertOk();

        Sanctum::actingAs(User::factory()->create());

        $this->star($plugin)->assertOk()->assertJsonPath('data.star_count', 2);

        $this->assertDatabaseCount('stars', 2);
    }

    // -----------------------------------------------------------------------
    // Idempotency
    // -----------------------------------------------------------------------

    /**
     * The property the whole endpoint exists to provide. A client that retries
     * after a timeout, or a user who double taps, must not inflate the count.
     */
    public function test_starring_twice_keeps_the_count_at_one(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->star($plugin)->assertOk()->assertJsonPath('data.star_count', 1);
        $this->star($plugin)->assertOk()->assertJsonPath('data.star_count', 1);

        $this->assertDatabaseCount('stars', 1);
    }

    public function test_unstarring_a_plugin_that_was_not_starred_returns_200(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->star($plugin, ['starred' => false])
            ->assertOk()
            ->assertJsonPath('data.starred', false)
            ->assertJsonPath('data.star_count', 0);

        $this->assertDatabaseCount('stars', 0);
    }

    public function test_unstarring_twice_keeps_the_count_at_zero(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $this->star($plugin)->assertOk();

        $this->star($plugin, ['starred' => false])->assertOk()->assertJsonPath('data.star_count', 0);
        $this->star($plugin, ['starred' => false])->assertOk()->assertJsonPath('data.star_count', 0);
    }

    public function test_unstarring_does_not_remove_another_users_star(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($owner);
        $plugin = $this->approvedPlugin();
        $this->star($plugin)->assertOk();

        Sanctum::actingAs($other);
        $this->star($plugin, ['starred' => false])->assertOk()->assertJsonPath('data.star_count', 1);

        $this->assertDatabaseHas('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $owner->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // Transaction and timestamps
    // -----------------------------------------------------------------------

    public function test_starring_does_not_touch_the_plugin_updated_at(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $before = $plugin->updated_at;

        $this->star($plugin)->assertOk();

        $this->assertTrue($before->equalTo($plugin->fresh()->updated_at));
    }

    /**
     * `stars` and `plugins.star_count` are one fact stored twice. If the counter
     * write fails after the row landed, the user would be starred with a count no
     * later request could repair — so the transaction has to take both back.
     */
    public function test_a_failing_counter_update_rolls_back_the_star(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();

        // Let every real call through, then fail the counter bump.
        $real = new PluginRepository($this->app);

        $failing = new class($real) implements PluginRepositoryInterface
        {
            public function __construct(private readonly PluginRepository $real) {}

            public function getModel(): string
            {
                return $this->real->getModel();
            }

            public function findApprovedById(string $id): Plugin
            {
                return $this->real->findApprovedById($id);
            }

            public function incrementCommentCount(string $pluginId): void
            {
                $this->real->incrementCommentCount($pluginId);
            }

            public function changeStarCount(string $pluginId, int $amount): void
            {
                throw new RuntimeException('star counter exploded');
            }

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function create(array $attributes): Plugin
            {
                return $this->real->create($attributes);
            }

            public function getPaginatedApprovedPlugins(int $perPage): LengthAwarePaginator
            {
                return $this->real->getPaginatedApprovedPlugins($perPage);
            }

            public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit): Collection
            {
                return $this->real->getTrendingPlugins($daysLimit, $weights, $gravity, $ageOffset, $limit);
            }

            public function getTopAllTimePlugins(int $limit): Collection
            {
                return $this->real->getTopAllTimePlugins($limit);
            }
        };

        $this->app->instance(PluginRepositoryInterface::class, $failing);

        try {
            $this->app->make(StarService::class)->setStarred($user, $plugin->id, true, new RequestContext(null, null));

            $this->fail('Expected the service to propagate the failure.');
        } catch (RuntimeException) {
            // Expected — the counter update blew up inside the transaction.
        }

        $this->assertDatabaseCount('stars', 0);
        $this->assertSame(0, $plugin->fresh()->star_count);
    }

    // -----------------------------------------------------------------------
    // Authentication
    // -----------------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/star", ['starred' => true])
            ->assertUnauthorized();
    }

    // -----------------------------------------------------------------------
    // Plugin guards
    // -----------------------------------------------------------------------

    public function test_returns_404_for_a_non_existent_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins/'.fake()->uuid().'/star', ['starred' => true])
            ->assertNotFound();
    }

    public function test_returns_404_for_a_pending_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        $this->star($plugin)->assertNotFound();

        $this->assertDatabaseCount('stars', 0);
    }

    public function test_returns_404_for_a_soft_deleted_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $plugin->delete();

        $this->star($plugin)->assertNotFound();

        $this->assertDatabaseCount('stars', 0);
    }

    // -----------------------------------------------------------------------
    // Input validation
    // -----------------------------------------------------------------------

    public function test_rejects_a_missing_starred_field(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->star($plugin, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starred']);
    }

    public function test_rejects_a_non_boolean_starred_field(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->star($plugin, ['starred' => 'yes'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starred']);
    }

    // -----------------------------------------------------------------------
    // Rate limiting
    // -----------------------------------------------------------------------

    public function test_starring_is_rate_limited_per_user(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $limit = (int) config('rate_limits.star_plugin_per_minute');

        // Repeating the same star is safe — that is what set-state buys — so the
        // limiter is what actually ends the burst.
        for ($i = 0; $i < $limit; $i++) {
            $this->star($plugin)->assertOk();
        }

        $this->star($plugin)->assertTooManyRequests();
    }

    /**
     * A limiter configured to zero would otherwise reject every request, taking
     * the endpoint down on a bad deploy. resolveRateLimit() floors it at 1.
     */
    public function test_rate_limit_never_blocks_everything_when_config_is_invalid(): void
    {
        config()->set('rate_limits.star_plugin_per_minute', 0);

        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->star($plugin)->assertOk();
    }
}
