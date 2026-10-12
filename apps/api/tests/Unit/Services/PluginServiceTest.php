<?php

namespace Tests\Unit\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Events\Plugin\PluginUpdated;
use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginService;
use App\Support\RequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PDOException;
use Tests\TestCase;

class PluginServiceTest extends TestCase
{
    // update() now writes a timeline entry alongside the plugin row, so the
    // cases that reach it need a real plugin_events table even though the
    // plugin repository itself stays mocked.
    use RefreshDatabase;

    public function test_duplicate_database_constraint_is_exposed_as_a_name_validation_error(): void
    {
        $repository = Mockery::mock(PluginRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'sqlite',
                'insert into plugins',
                [],
                new PDOException('UNIQUE constraint failed: plugins.user_id, plugins.name'),
            ));

        $this->expectException(ValidationException::class);

        try {
            app(PluginService::class, ['pluginRepository' => $repository])
                ->submit(User::factory()->make(), [
                    'name' => 'Laravel Debugbar',
                    'title' => 'Debugbar for Laravel',
                    'license' => 'MIT',
                    'source_link' => 'https://github.com/barryvdh/laravel-debugbar',
                ], new RequestContext(null, null));
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['You already submitted a plugin with this name.'],
                $exception->errors()['name'],
            );

            throw $exception;
        }
    }

    /**
     * Mirrors the submit() case above: renaming onto a name this user already
     * holds surfaces as a field-level validation error rather than a 500.
     */
    public function test_duplicate_database_constraint_on_update_is_exposed_as_a_name_validation_error(): void
    {
        $user = User::factory()->make();

        // An explicit id, because `make()` does not assign the model's UUID.
        // The service now writes a timeline entry keyed on this plugin, and a
        // persisted plugin always has a key — a null one here would only ever
        // be an artefact of the factory.
        $plugin = Plugin::factory()->make([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
        ]);

        $pluginId = $plugin->getKey();

        $repository = Mockery::mock(PluginRepositoryInterface::class);
        $repository->shouldReceive('findById')
            ->once()
            ->with($pluginId)
            ->andReturn($plugin);
        $repository->shouldReceive('update')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'sqlite',
                'update plugins',
                [],
                new PDOException('UNIQUE constraint failed: plugins.user_id, plugins.name'),
            ));

        $this->expectException(ValidationException::class);

        try {
            app(PluginService::class, ['pluginRepository' => $repository])
                ->update($user, $pluginId, ['name' => 'Laravel Debugbar'], new RequestContext(null, null));
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['You already submitted a plugin with this name.'],
                $exception->errors()['name'],
            );

            throw $exception;
        }
    }

    public function test_updating_a_plugin_owned_by_another_user_is_refused(): void
    {
        // Both models use HasUuids, so factory()->make() leaves the key null on
        // each — two nulls would compare equal and the check would pass. Set the
        // keys explicitly: this test is about two *different* owners.
        $owner = User::factory()->make()->forceFill(['id' => 'owner-id']);
        $intruder = User::factory()->make()->forceFill(['id' => 'intruder-id']);
        $plugin = Plugin::factory()->make(['user_id' => $owner->id]);

        $repository = Mockery::mock(PluginRepositoryInterface::class);
        $repository->shouldReceive('findById')
            ->once()
            ->andReturn($plugin);
        // The write must never be attempted: the refusal happens before it.
        $repository->shouldNotReceive('update');

        $this->expectException(AuthorizationException::class);

        app(PluginService::class, ['pluginRepository' => $repository])
            ->update($intruder, (string) $plugin->getKey(), ['title' => 'Đã bị chiếm'], new RequestContext(null, null));
    }

    public function test_update_passes_the_validated_payload_through_untouched(): void
    {
        // The event carries models, so dispatching it would try to resolve the
        // row in a test that never created one. Faked to keep this a unit test
        // about the hand-off to the repository; the event itself is covered by
        // PluginUpdateEventTest.
        Event::fake([PluginUpdated::class]);

        // Created rather than made: alongside the plugin row, update() now
        // writes the timeline entry that references it, so both the plugin and
        // its owner need real rows for the foreign keys to hold.
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $pluginId = $plugin->getKey();

        $attributes = ['title' => 'Debugbar mới'];

        $repository = Mockery::mock(PluginRepositoryInterface::class);
        $repository->shouldReceive('findById')
            ->once()
            ->with($pluginId)
            ->andReturn($plugin);
        $repository->shouldReceive('update')
            ->once()
            ->with($plugin, $attributes)
            ->andReturn($plugin);

        $this->assertSame(
            $plugin,
            app(PluginService::class, ['pluginRepository' => $repository])
                ->update($user, $pluginId, $attributes, new RequestContext(null, null)),
        );
    }
}
