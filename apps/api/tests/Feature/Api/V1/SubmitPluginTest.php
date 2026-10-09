<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Events\Plugin\PluginSubmitted;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'category' => 'Developer tools',
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

        // `star_count` / `comment_count` are not columns, so they cannot be asserted
        // here — the two row counts above already stand for them, and the
        // response assertions above check the values the client actually reads.
        $this->assertDatabaseHas('plugins', [
            'user_id' => $user->id,
            'name' => 'Laravel Debugbar',
            'status' => PluginStatus::Pending->value,
            'view_count' => 0,
            'approved_at' => null,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('stars', 0);
        $this->assertDatabaseCount('comments', 0);
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
            'view_count' => 0,
            'approved_at' => null,
        ]);
        $this->assertDatabaseCount('stars', 0);
        $this->assertDatabaseCount('comments', 0);
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

    /**
     * `max` rules must reject one character past the limit. Each boundary is
     * asserted from both sides so an off-by-one in the rule is caught.
     *
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function maxLengthFieldProvider(): array
    {
        return [
            'name' => ['name', 'name', 255],
            'title' => ['title', 'title', 255],
            'source_link' => ['source_link', 'source link', 2048],
        ];
    }

    #[DataProvider('maxLengthFieldProvider')]
    public function test_submission_rejects_fields_longer_than_their_max_length(string $field, string $label, int $max): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Keep `source_link` a valid URL so `url:https` cannot fail first and
        // hide the `max` failure behind an earlier error message.
        $value = $field === 'source_link'
            ? 'https://example.com/'.str_repeat('a', $max + 1 - strlen('https://example.com/'))
            : str_repeat('a', $max + 1);

        $this->postJson('/api/v1/plugins', $this->payload([
            $field => $value,
        ]))->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors([$field])
            ->assertJsonPath('errors.'.$field.'.0', 'The '.$label.' field must not be greater than '.$max.' characters.');

        $this->assertDatabaseCount('plugins', 0);
    }

    #[DataProvider('maxLengthFieldProvider')]
    public function test_submission_accepts_fields_exactly_at_their_max_length(string $field, string $label, int $max): void
    {
        Sanctum::actingAs(User::factory()->create());

        // The generated value is a valid URL so `source_link` passes `url:https`
        // and only the `max` rule is under test.
        $value = $field === 'source_link'
            ? 'https://example.com/'.str_repeat('a', $max - strlen('https://example.com/'))
            : str_repeat('a', $max);

        $this->postJson('/api/v1/plugins', $this->payload([$field => $value]))
            ->assertCreated()
            ->assertJsonPath('data.plugin.'.$field, $value);

        $this->assertSame($max, strlen($value), ucfirst($label).' should sit exactly on the limit.');

        $this->assertDatabaseCount('plugins', 1);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function wrongTypeFieldProvider(): array
    {
        return [
            'name as array' => ['name', ['Laravel Debugbar']],
            'name as nested array' => ['name', ['nested' => ['value']]],
            'name as integer' => ['name', 12345],
            'title as float' => ['title', 1.5],
            'title as boolean' => ['title', true],
            'license as array' => ['license', ['MIT']],
            'source_link as array' => ['source_link', ['https://example.com/plugin']],
        ];
    }

    #[DataProvider('wrongTypeFieldProvider')]
    public function test_submission_rejects_fields_with_the_wrong_data_type(string $field, mixed $value): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', $this->payload([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors([$field]);

        $this->assertDatabaseCount('plugins', 0);
    }

    /**
     * A string of nothing but whitespace must be rejected. Note that Laravel's
     * `TrimStrings` middleware and the `required` rule already cover this, so
     * this asserts the API contract rather than the hand-written trim. The trim
     * itself is covered by `SubmitPluginRequestTest`.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function whitespaceOnlyFieldProvider(): array
    {
        return [
            'name of spaces' => ['name', 'name', '     '],
            'name of tabs and newlines' => ['name', 'name', "\t\n\r  "],
            'title of spaces' => ['title', 'title', '   '],
            'license of spaces' => ['license', 'license', '  '],
            'source_link of spaces' => ['source_link', 'source link', '     '],
        ];
    }

    #[DataProvider('whitespaceOnlyFieldProvider')]
    public function test_submission_rejects_fields_containing_only_whitespace(string $field, string $label, string $value): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', $this->payload([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors([$field])
            ->assertJsonPath('errors.'.$field.'.0', 'The '.$label.' field is required.');

        $this->assertDatabaseCount('plugins', 0);
    }

    /**
     * A padded value that is within the limit once trimmed must be accepted,
     * which requires trimming to happen before the `max` rule runs.
     */
    public function test_submission_accepts_a_padded_name_that_fits_the_limit_once_trimmed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $name = '  '.str_repeat('a', 255).'  ';

        $this->assertSame(259, strlen($name));

        $this->postJson('/api/v1/plugins', $this->payload(['name' => $name]))
            ->assertCreated()
            ->assertJsonPath('data.plugin.name', str_repeat('a', 255));

        $this->assertDatabaseHas('plugins', ['name' => str_repeat('a', 255)]);
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

        $limit = (int) config('rate_limits.submit_plugin_per_minute');

        for ($index = 0; $index < $limit; $index++) {
            $this->postJson('/api/v1/plugins', $this->payload([
                'name' => 'Plugin '.$index,
            ]))->assertCreated();
        }

        $this->postJson('/api/v1/plugins', $this->payload([
            'name' => 'Plugin '.$limit,
        ]))->assertTooManyRequests();
    }

    public function test_submit_rate_limit_is_configurable_per_environment(): void
    {
        config()->set('rate_limits.submit_plugin_per_minute', 1);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', $this->payload([
            'name' => 'First Plugin',
        ]))->assertCreated();

        $this->postJson('/api/v1/plugins', $this->payload([
            'name' => 'Second Plugin',
        ]))->assertTooManyRequests();
    }

    public function test_submit_rate_limit_never_blocks_everything_when_config_is_invalid(): void
    {
        config()->set('rate_limits.submit_plugin_per_minute', 0);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', $this->payload([
            'name' => 'Still Reachable Plugin',
        ]))->assertCreated();
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
