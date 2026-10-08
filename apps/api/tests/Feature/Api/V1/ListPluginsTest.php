<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListPluginsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_paginated_approved_plugins_sorted_by_approved_at_desc(): void
    {
        // Create an unapproved plugin (should not be listed)
        Plugin::factory()->create([
            'status' => PluginStatus::Pending,
        ]);

        // Create approved plugins
        $olderPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(2),
        ]);

        $newerPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/plugins');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            // Assert ordering: newest first
            ->assertJsonPath('data.0.id', $newerPlugin->id)
            ->assertJsonPath('data.1.id', $olderPlugin->id)
            // Assert paginated structure
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'title', 'status', 'approved_at'],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_does_not_return_approved_plugins_without_approved_at(): void
    {
        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => null,
        ]);

        $response = $this->getJson('/api/v1/plugins');
        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_returns_empty_when_no_approved_plugins(): void
    {
        $response = $this->getJson('/api/v1/plugins');
        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_respects_per_page_parameter(): void
    {
        Plugin::factory()->count(10)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins?per_page=3');
        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total', 10);
    }

    public function test_caps_per_page_at_config_max(): void
    {
        config()->set('plugins.pagination.max_per_page', 5);

        Plugin::factory()->count(10)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins?per_page=50');
        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_uses_default_per_page_when_not_specified(): void
    {
        config()->set('plugins.pagination.default_per_page', 2);

        Plugin::factory()->count(5)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_pagination_navigates_correctly(): void
    {
        Plugin::factory()->count(5)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins?per_page=2&page=2');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_returns_author_username(): void
    {
        $author = User::factory()->create(['name' => 'Ada Lovelace']);
        $author->profile()->create(['avatar_link' => 'https://example.com/avatar.png']);

        $plugin = Plugin::factory()->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins');

        $response->assertOk()
            ->assertJsonPath('data.0.author.id', $author->id)
            ->assertJsonPath('data.0.author.name', 'Ada Lovelace')
            ->assertJsonPath('data.0.author.avatar_url', 'https://example.com/avatar.png')
            ->assertJsonPath('data.0.user_id', $plugin->user_id);
    }

    public function test_author_avatar_url_is_null_when_profile_missing(): void
    {
        $author = User::factory()->create(['name' => 'Grace Hopper']);

        Plugin::factory()->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins');

        $response->assertOk()
            ->assertJsonPath('data.0.author.name', 'Grace Hopper')
            ->assertJsonPath('data.0.author.avatar_url', null);
    }

    public function test_does_not_expose_author_email(): void
    {
        $author = User::factory()->create();

        Plugin::factory()->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins');

        $response->assertOk()
            ->assertJsonMissingPath('data.0.author.email')
            ->assertJsonMissingPath('data.0.author.password')
            ->assertJsonMissingPath('data.0.author.is_admin');
    }

    public function test_does_not_n_plus_one_on_the_user_relation(): void
    {
        Plugin::factory()->count(5)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->getJson('/api/v1/plugins')->assertOk();

        // The plugins page plus its count query, plus exactly one query per
        // eager loaded relation (users, user_profiles, categories, tags) — all batched. Without
        // the eager loads this grows with the page size.
        $this->assertLessThanOrEqual(6, $queries);
    }

    public function test_response_uses_plugin_resource_format(): void
    {
        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins');
        $response->assertOk()
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
            ]);

        // Ensure sensitive fields are not exposed
        $response->assertJsonMissingPath('data.0.deleted_at');
    }

    // -----------------------------------------------------------------------
    // is_star
    // -----------------------------------------------------------------------

    /**
     * `stars` has no Eloquent model (composite primary key), so there is no
     * StarFactory to seed through. The table is written to directly instead.
     */
    private function givenStarred(Plugin $plugin, User $user): void
    {
        DB::table('stars')->insert([
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_marks_is_star_true_only_for_plugins_the_user_starred(): void
    {
        $user = User::factory()->create();

        $starred = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
        $notStarred = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);

        $this->givenStarred($starred, $user);

        Sanctum::actingAs($user, [], 'api');

        $this->getJson('/api/v1/plugins')
            ->assertOk()
            ->assertJsonPath('data.0.id', $starred->id)
            ->assertJsonPath('data.0.is_star', true)
            ->assertJsonPath('data.1.id', $notStarred->id)
            ->assertJsonPath('data.1.is_star', false);
    }

    public function test_guest_does_not_receive_the_is_star_field(): void
    {
        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->getJson('/api/v1/plugins')
            ->assertOk()
            ->assertJsonMissingPath('data.0.is_star');
    }

    public function test_is_star_is_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->givenStarred($plugin, $owner);

        Sanctum::actingAs($other, [], 'api');

        $this->getJson('/api/v1/plugins')
            ->assertOk()
            ->assertJsonPath('data.0.is_star', false);
    }
}
