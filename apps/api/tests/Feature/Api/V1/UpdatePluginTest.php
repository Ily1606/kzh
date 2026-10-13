<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use Database\Seeders\CategoryAndTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdatePluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The taxonomy tables are empty under RefreshDatabase, and the service
        // refuses a category it cannot find. Mirroring the config is what a
        // deployed environment does at seed time.
        $this->seed(CategoryAndTagSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pluginFor(User $user, array $overrides = [], ?string $category = null, array $tags = []): Plugin
    {
        $factory = Plugin::factory()->withTags($tags);

        if ($category !== null) {
            $factory = $factory->withCategory($category);
        }

        return $factory->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Laravel Debugbar',
            'title' => 'Debugbar for Laravel',
            'license' => 'MIT',
            'source_link' => 'https://github.com/barryvdh/laravel-debugbar',
        ], $overrides));
    }

    /**
     * Star count is COUNT over `stars`, so tests seed rows — one distinct
     * user per star, the composite primary key forbids duplicates.
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
     * Comment count is COUNT over `comments` for the same reason, so a test that
     * wants a plugin to "have N comments" has to create the rows.
     */
    private function commentTimes(Plugin $plugin, int $count): void
    {
        Comment::factory()->count($count)->create([
            'plugin_id' => $plugin->id,
        ]);
    }

    public function test_owner_can_update_the_four_editable_fields(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'name' => 'Laravel Debugbar Renamed',
            'title' => 'Debugbar cho Laravel',
            'license' => 'GPL-3.0',
            'source_link' => 'https://github.com/barryvdh/laravel-debugbar/tree/3.x',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plugin updated successfully.')
            ->assertJsonPath('data.plugin.id', $plugin->id)
            ->assertJsonPath('data.plugin.name', 'Laravel Debugbar Renamed')
            ->assertJsonPath('data.plugin.title', 'Debugbar cho Laravel')
            ->assertJsonPath('data.plugin.license', 'GPL-3.0')
            ->assertJsonPath('data.plugin.source_link', 'https://github.com/barryvdh/laravel-debugbar/tree/3.x');

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'name' => 'Laravel Debugbar Renamed',
            'title' => 'Debugbar cho Laravel',
            'license' => 'GPL-3.0',
            'source_link' => 'https://github.com/barryvdh/laravel-debugbar/tree/3.x',
        ]);
    }

    public function test_update_only_touches_the_fields_present_in_the_body(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Debugbar mới',
        ])->assertOk()
            ->assertJsonPath('data.plugin.title', 'Debugbar mới')
            ->assertJsonPath('data.plugin.name', 'Laravel Debugbar')
            ->assertJsonPath('data.plugin.license', 'MIT')
            ->assertJsonPath('data.plugin.source_link', 'https://github.com/barryvdh/laravel-debugbar');

        $plugin->refresh();

        $this->assertSame('Debugbar mới', $plugin->title);
        $this->assertSame('Laravel Debugbar', $plugin->name);
        $this->assertSame('MIT', $plugin->license);
    }

    /**
     * The whole point of the endpoint: a client posting system-managed fields
     * gets them ignored, not honoured. `validated()` is the only gate, so this
     * test is what keeps a future rule edit from quietly reopening the hole.
     */
    public function test_system_managed_fields_are_never_writable(): void
    {
        $user = User::factory()->create();
        $approvedAt = now()->subDay()->startOfSecond();
        $plugin = $this->pluginFor($user, [
            'status' => PluginStatus::Approved,
            'approved_at' => $approvedAt,
            'view_count' => 100,
        ]);
        $this->starTimes($plugin, 5);
        $this->commentTimes($plugin, 3);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Vẫn sửa được',
            'star_count' => 9999,
            'comment_count' => 8888,
            'view_count' => 7777,
            'status' => PluginStatus::Rejected->value,
            'approved_at' => '2020-01-01T00:00:00Z',
        ])->assertOk();

        $plugin->refresh();

        $this->assertSame(5, $plugin->stars()->count());
        $this->assertSame(3, $plugin->comments()->count());
        $this->assertSame(100, $plugin->view_count);
        $this->assertSame(PluginStatus::Approved, $plugin->status);
        $this->assertTrue($plugin->approved_at->equalTo($approvedAt));

        // The editable field in the same body still went through.
        $this->assertSame('Vẫn sửa được', $plugin->title);

        $response->assertJsonPath('data.plugin.star_count', 5)
            ->assertJsonPath('data.plugin.comment_count', 3)
            ->assertJsonPath('data.plugin.view_count', 100)
            ->assertJsonPath('data.plugin.status', PluginStatus::Approved->value);
    }

    public function test_ownership_cannot_be_transferred_through_the_body(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $plugin = $this->pluginFor($owner);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Vẫn của tôi',
            'user_id' => $other->id,
        ])->assertOk();

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'user_id' => $owner->id,
            'title' => 'Vẫn của tôi',
        ]);
    }

    public function test_editing_an_approved_plugin_leaves_its_review_state_untouched(): void
    {
        $user = User::factory()->create();
        $approvedAt = now()->subDays(3)->startOfSecond();
        $plugin = $this->pluginFor($user, [
            'status' => PluginStatus::Approved,
            'approved_at' => $approvedAt,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Sửa sau khi duyệt',
        ])->assertOk()
            ->assertJsonPath('data.plugin.status', PluginStatus::Approved->value)
            ->assertJsonPath('data.plugin.approved_at', $approvedAt->toISOString());

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'status' => PluginStatus::Approved->value,
            'title' => 'Sửa sau khi duyệt',
        ]);
    }

    /**
     * A pending plugin belongs to its owner, who has every right to correct it
     * before an admin sees it. Regression guard against reaching for
     * findApprovedById() here, which would 404 every pending plugin.
     */
    public function test_owner_can_update_a_plugin_that_is_still_pending(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, [
            'status' => PluginStatus::Pending,
            'approved_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Sửa trước khi duyệt',
        ])->assertOk()
            ->assertJsonPath('data.plugin.status', PluginStatus::Pending->value)
            ->assertJsonPath('data.plugin.title', 'Sửa trước khi duyệt')
            ->assertJsonPath('data.plugin.approved_at', null);
    }

    public function test_a_plugin_owned_by_someone_else_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $plugin = $this->pluginFor($owner);
        Sanctum::actingAs($intruder);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Đã bị chiếm',
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.unauthorized'));

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'title' => 'Debugbar for Laravel',
        ]);
    }

    public function test_update_requires_authentication(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'Không token'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_unknown_plugin_is_a_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/plugins/00000000-0000-4000-8000-000000000000', [
            'title' => 'Không tồn tại',
        ])->assertNotFound();
    }

    public function test_soft_deleted_plugin_is_a_not_found(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        $plugin->delete();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Đã xóa',
        ])->assertNotFound();
    }

    public function test_renaming_to_a_name_the_user_already_holds_is_a_validation_error(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, ['name' => 'Debugbar']);
        Plugin::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laravel Debugbar',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'name' => 'Laravel Debugbar',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.name.0', 'You already submitted a plugin with this name.');

        $this->assertDatabaseHas('plugins', ['id' => $plugin->id, 'name' => 'Debugbar']);
    }

    /**
     * A no-op edit answering 200 reads as a successful change while nothing
     * changed, which is how a client bug stays invisible.
     */
    public function test_a_body_with_no_editable_field_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.plugin.0', __('api.plugin_update_requires_field'));
    }

    /**
     * A body made only of fields the client wrongly believes are editable is
     * empty as far as this endpoint is concerned: it must not slip through as
     * a 200 just because it was not literally `{}`.
     */
    public function test_a_body_of_only_system_managed_fields_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'star_count' => 9999,
            'status' => PluginStatus::Rejected->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.plugin.0', __('api.plugin_update_requires_field'));

        $this->assertDatabaseCount('stars', 0);
    }

    public function test_invalid_license_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'license' => 'Not-A-License',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license');
    }

    public function test_non_https_source_link_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'source_link' => 'http://example.com/insecure',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_link');
    }

    public function test_overlong_name_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'name' => str_repeat('a', 256),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_strings_are_trimmed_before_validation(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => '  Debugbar mới  ',
        ])->assertOk()
            ->assertJsonPath('data.plugin.title', 'Debugbar mới');

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'title' => 'Debugbar mới',
        ]);
    }

    public function test_response_uses_the_plugin_resource_contract(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Contract Plugin',
        ])->assertOk();

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'plugin' => [
                    'id',
                    'name',
                    'user_id',
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
            'errors',
        ]);

        $response->assertJsonMissingPath('data.plugin.deleted_at');
    }

    // -----------------------------------------------------------------------
    // category & tags
    // -----------------------------------------------------------------------

    public function test_the_category_alone_is_a_valid_edit(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, category: 'Integrations');
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['category' => 'Vision'])
            ->assertOk()
            ->assertJsonPath('data.plugin.category', 'Vision');

        $this->assertDatabaseHas('category_plugin', [
            'plugin_id' => $plugin->id,
            'category_id' => Category::query()->where('name', 'Vision')->value('id'),
        ]);
    }

    public function test_the_tags_alone_are_a_valid_edit(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['tags' => ['May read files']])
            ->assertOk()
            ->assertJsonPath('data.plugin.tags', ['May read files']);

        $this->assertDatabaseCount('plugin_tag', 1);
    }

    /**
     * An edit that leaves one field out must not touch it: the pivots are
     * synced only when their key is present in the body.
     */
    public function test_editing_the_category_leaves_the_tags_alone(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, category: 'Integrations', tags: ['May read files']);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['category' => 'Vision'])
            ->assertOk()
            ->assertJsonPath('data.plugin.category', 'Vision')
            ->assertJsonPath('data.plugin.tags', ['May read files']);
    }

    public function test_editing_the_tags_leaves_the_category_alone(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, category: 'Integrations');
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['tags' => ['May run commands']])
            ->assertOk()
            ->assertJsonPath('data.plugin.category', 'Integrations')
            ->assertJsonPath('data.plugin.tags', ['May run commands']);
    }

    /**
     * An empty array is how an owner clears the tags they no longer want. It
     * has to be distinguishable from an absent key, or "clear" and "leave as
     * is" would be the same request.
     */
    public function test_an_empty_tag_list_clears_the_tags(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, category: 'Integrations', tags: ['May read files', 'May run commands']);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['tags' => []])
            ->assertOk()
            ->assertJsonPath('data.plugin.tags', []);

        $this->assertDatabaseCount('plugin_tag', 0);
        $this->assertDatabaseCount('category_plugin', 1);
    }

    public function test_a_category_outside_the_configured_list_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, category: 'Integrations');
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['category' => 'Cryptocurrency'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->assertDatabaseHas('category_plugin', [
            'plugin_id' => $plugin->id,
            'category_id' => Category::query()->where('name', 'Integrations')->value('id'),
        ]);
    }

    public function test_a_tag_outside_the_configured_list_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['tags' => ['Runs arbitrary shell']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tags.0']);
    }

    /**
     * Padded tags must not fail the configured-list rule. The global
     * `TrimStrings` middleware is what strips them, and it runs on every method.
     */
    public function test_padded_tags_are_accepted(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'tags' => ['  May read files  ', "\tMay run commands\n"],
        ])->assertOk()
            ->assertJsonPath('data.plugin.tags', ['May read files', 'May run commands']);
    }

    public function test_a_whitespace_only_tag_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['tags' => ['May read files', '  ']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tags.1']);
    }

    public function test_tags_sent_as_a_string_rather_than_an_array_is_rejected(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['tags' => 'May read files'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags');
    }

    public function test_a_refused_edit_leaves_the_plugin_untouched(): void
    {
        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, category: 'Integrations');
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'A Title That Should Not Stick',
            'tags' => ['Runs arbitrary shell'],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'title' => 'Debugbar for Laravel',
        ]);
    }
}
