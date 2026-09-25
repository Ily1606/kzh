<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Events\Plugin\PluginSubmitted;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmitPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([PluginSubmitted::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Laravel Debugbar',
            'title' => 'Debugbar for Laravel',
            'license' => 'MIT',
            'source_link' => 'https://github.com/barryvdh/laravel-debugbar',
        ], $overrides);
    }

    public function test_authenticated_user_can_submit_a_plugin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/plugins', $this->payload([
            'name' => '  Laravel Debugbar  ',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plugin submitted successfully.')
            ->assertJsonPath('data.plugin.name', 'Laravel Debugbar')
            ->assertJsonPath('data.plugin.user_id', $user->id)
            ->assertJsonPath('data.plugin.status', 'pending')
            ->assertJsonPath('data.plugin.star_count', 0)
            ->assertJsonPath('data.plugin.comment_count', 0)
            ->assertJsonPath('data.plugin.view_count', 0)
            ->assertJsonPath('data.plugin.approved_at', null)
            ->assertJsonMissingPath('data.plugin.deleted_at');

        $this->assertDatabaseHas('plugins', [
            'user_id' => $user->id,
            'name' => 'Laravel Debugbar',
            'status' => PluginStatus::Pending->value,
            'star_count' => 0,
            'comment_count' => 0,
            'view_count' => 0,
            'approved_at' => null,
            'deleted_at' => null,
        ]);
    }

    public function test_response_uses_the_plugin_resource_contract(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/plugins', $this->payload([
            'name' => 'Resource Contract Plugin',
        ]))->assertCreated();

        $response->assertJsonStructure([
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
        ]);

        $response->assertJsonMissingPath('data.plugin.deleted_at');
    }

    public function test_submission_requires_authentication(): void
    {
        $this->postJson('/api/v1/plugins', $this->payload())
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertDatabaseCount('plugins', 0);
    }

    public function test_client_cannot_override_server_controlled_fields(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/plugins', $this->payload([
            'user_id' => $otherUser->id,
            'status' => PluginStatus::Approved->value,
            'approved_at' => now()->toDateTimeString(),
            'star_count' => 999,
            'comment_count' => 999,
            'view_count' => 999,
        ]))->assertCreated()
            ->assertJsonPath('data.plugin.user_id', $user->id)
            ->assertJsonPath('data.plugin.status', PluginStatus::Pending->value)
            ->assertJsonPath('data.plugin.star_count', 0)
            ->assertJsonPath('data.plugin.comment_count', 0)
            ->assertJsonPath('data.plugin.view_count', 0)
            ->assertJsonPath('data.plugin.approved_at', null);

        $this->assertDatabaseHas('plugins', [
            'user_id' => $user->id,
            'status' => PluginStatus::Pending->value,
            'star_count' => 0,
            'comment_count' => 0,
            'view_count' => 0,
            'approved_at' => null,
        ]);
    }

    public function test_submission_requires_all_business_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'title', 'license', 'source_link']);
    }

    public function test_submission_rejects_invalid_license_and_non_https_source_link(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', $this->payload([
            'license' => 'UNLICENSED',
            'source_link' => 'http://example.com/plugin',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['license', 'source_link']);
    }

    public function test_license_validation_uses_the_configured_list(): void
    {
        config()->set('plugins.licenses', ['Custom-License']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', $this->payload([
            'license' => 'Custom-License',
        ]))->assertCreated();

        $this->postJson('/api/v1/plugins', $this->payload([
            'name' => 'Another Plugin',
            'license' => 'MIT',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('license');
    }

    public function test_submission_is_rate_limited_per_authenticated_user(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($index = 0; $index < 5; $index++) {
            $this->postJson('/api/v1/plugins', $this->payload([
                'name' => 'Plugin '.$index,
            ]))->assertCreated();
        }

        $this->postJson('/api/v1/plugins', $this->payload([
            'name' => 'Plugin 5',
        ]))->assertTooManyRequests();
    }

    public function test_same_user_cannot_submit_the_same_name_twice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/plugins', $this->payload())->assertCreated();

        $this->postJson('/api/v1/plugins', $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.name.0', 'You already submitted a plugin with this name.');

        $this->assertDatabaseCount('plugins', 1);
    }

    public function test_different_users_can_submit_the_same_name(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/plugins', $this->payload())->assertCreated();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/plugins', $this->payload())->assertCreated();

        $this->assertDatabaseCount('plugins', 2);
    }

    public function test_soft_deleted_plugin_keeps_its_name_reserved(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laravel Debugbar',
        ]);
        $plugin->delete();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/plugins', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('plugins', 1);
    }
}
