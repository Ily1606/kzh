<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListMyPluginsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_my_plugins(): void
    {
        $response = $this->getJson('/api/v1/user/plugins');

        $response->assertStatus(401);
    }

    public function test_returns_plugins_of_every_review_status(): void
    {
        $user = User::factory()->create();

        $pending = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);
        $approved = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
        $rejected = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Rejected,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/plugins');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);

        // This is the contrast that makes the test meaningful: the public list
        // reports none of these, because none of them is a finished catalogue
        // entry the way the approved one is.
        $this->assertEqualsCanonicalizing(
            [$pending->id, $approved->id, $rejected->id],
            array_column($response->json('data'), 'id'),
        );

        $response->assertJsonFragment(['status' => 'pending']);
        $response->assertJsonFragment(['status' => 'approved']);
        $response->assertJsonFragment(['status' => 'rejected']);
    }

    public function test_does_not_return_plugins_owned_by_someone_else(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
        Plugin::factory()->create([
            'user_id' => $other->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.user_id', $user->id);
    }

    public function test_filters_by_status(): void
    {
        $user = User::factory()->create();

        $pending = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);
        Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/plugins?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_approved_filter_uses_the_approved_enum_value(): void
    {
        // The tab is labelled "Published", the enum value is "approved". The
        // wire contract has to stay the enum value or the tab silently returns
        // nothing.
        $user = User::factory()->create();

        $approved = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
        Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);
    }

    public function test_rejects_an_unknown_status(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Silently ignoring a bad filter would hand back an unfiltered list,
        // which reads as a correct answer for a filter that never applied.
        $this->getJson('/api/v1/user/plugins?status=published')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_does_not_return_a_soft_deleted_plugin(): void
    {
        $user = User::factory()->create();

        Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);
        $trashed = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);
        $trashed->delete();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['id' => $trashed->id]);
    }

    public function test_sorts_by_creation_time_desc(): void
    {
        $user = User::factory()->create();

        $older = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
            'created_at' => now()->subDays(2),
        ]);
        $newer = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_orders_by_created_at_not_approved_at(): void
    {
        // A pending row has a null approved_at. Sorting on it the way the
        // public list does would drop those rows to the bottom or lose them,
        // which is exactly the half of the list the owner needs to see.
        $user = User::factory()->create();

        $pending = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
            'approved_at' => null,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.approved_at', null);
    }

    public function test_returns_empty_when_the_user_has_no_plugins(): void
    {
        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_paginates(): void
    {
        $user = User::factory()->create();
        Plugin::factory()->count(5)->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_caps_per_page_at_config_max(): void
    {
        config()->set('plugins.pagination.max_per_page', 5);

        $user = User::factory()->create();
        Plugin::factory()->count(10)->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins?per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_uses_default_per_page_when_not_specified(): void
    {
        config()->set('plugins.pagination.default_per_page', 2);

        $user = User::factory()->create();
        Plugin::factory()->count(5)->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_counts_stars_without_a_query_per_row(): void
    {
        // PluginResource falls back to `$plugin->stars()->count()` when
        // `star_count` is missing, so the repository has to supply it or the
        // page size becomes the query count.
        $user = User::factory()->create();

        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        User::factory()->count(3)->create()->each(function (User $stargazer) use ($plugin): void {
            DB::table('stars')->insert([
                'plugin_id' => $plugin->id,
                'user_id' => $stargazer->id,
            ]);
        });

        Sanctum::actingAs($user);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonPath('data.0.star_count', 3);

        // Page + count + users + user_profiles, all batched.
        $this->assertLessThanOrEqual(5, $queries);
    }

    public function test_marks_is_star_only_on_approved_plugins(): void
    {
        $user = User::factory()->create();

        // `stars` has no Eloquent model (composite primary key), so there is
        // no StarFactory to seed through. The table is written to directly.
        $starred = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);
        $approvedNotStarred = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
        $pending = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);

        DB::table('stars')->insert([
            'plugin_id' => $starred->id,
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/plugins');

        $response->assertOk()
            ->assertJsonPath('data.2.id', $starred->id)
            ->assertJsonPath('data.2.is_star', true)
            ->assertJsonPath('data.1.id', $approvedNotStarred->id)
            ->assertJsonPath('data.1.is_star', false)
            // A star row cannot exist on a plugin that is not approved:
            // StarService resolves through findApprovedById, which throws
            // before it writes. Reporting `false` here would assert the caller
            // has not starred a plugin they cannot star, so the field is
            // absent instead.
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonMissingPath('data.0.is_star');
    }

    public function test_rejected_plugin_carries_no_is_star_field(): void
    {
        $user = User::factory()->create();

        Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Rejected,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'rejected')
            ->assertJsonMissingPath('data.0.is_star');
    }

    public function test_does_not_query_stars_when_the_status_filter_excludes_approved(): void
    {
        $user = User::factory()->create();

        Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->getJson('/api/v1/user/plugins?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.is_star');

        // The approved subset of the page is empty, so there is nothing to ask
        // the stars table about — one less query on a list that renders no
        // star toggle at all. Matched on the pluck the flag resolution issues;
        // the repository's own `withCount` subquery also mentions "stars".
        $this->assertSame(
            [],
            array_values(array_filter(
                $queries,
                fn (string $sql): bool => str_contains($sql, 'select "plugin_id" from "stars"'),
            )),
        );
    }

    public function test_response_uses_plugin_resource_format(): void
    {
        $user = User::factory()->create();
        Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/plugins')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'user_id',
                        'author' => ['id', 'name', 'avatar_url'],
                        'title',
                        'license',
                        'approved_at',
                        'status',
                        'source_link',
                        'star_count',
                        'comment_count',
                        'view_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonMissingPath('data.0.deleted_at');
    }
}