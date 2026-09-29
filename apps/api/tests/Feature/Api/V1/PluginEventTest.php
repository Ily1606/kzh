<?php

namespace Tests\Feature\Api\V1;

use App\Events\Plugin\PluginSubmitted;
use App\Listeners\Plugin\LogPluginSubmission;
use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginService;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PluginEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Event Plugin',
            'title' => 'Event Plugin Title',
            'license' => 'MIT',
            'source_link' => 'https://example.com/event-plugin',
        ], $overrides);
    }

    public function test_submit_dispatches_plugin_submitted_event_with_plugin_user_and_context(): void
    {
        Event::fake([PluginSubmitted::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('User-Agent', 'Plugin Review Agent')
            ->postJson('/api/v1/plugins', $this->payload())
            ->assertCreated();

        Event::assertDispatched(
            PluginSubmitted::class,
            function (PluginSubmitted $event) use ($user): bool {
                return $event->plugin->user_id === $user->id
                    && $event->user->is($user)
                    && $event->context()['plugin_id'] === $event->plugin->id
                    && $event->context()['user_id'] === $user->id
                    && $event->context()['name'] === 'Event Plugin'
                    && $event->context()['status'] === 'pending'
                    && $event->context()['ip_address'] === '127.0.0.1'
                    && $event->context()['user_agent'] === 'Plugin Review Agent';
            },
        );
    }

    public function test_event_constructor_preserves_explicit_request_metadata(): void
    {
        $plugin = Plugin::factory()->make();
        $user = User::factory()->make();

        $event = new PluginSubmitted(
            plugin: $plugin,
            user: $user,
            ipAddress: '192.0.2.10',
            userAgent: 'Plugin Review Agent',
        );

        $this->assertSame('192.0.2.10', $event->ipAddress);
        $this->assertSame('192.0.2.10', $event->context()['ip_address']);
        $this->assertSame('Plugin Review Agent', $event->userAgent);
        $this->assertSame('Plugin Review Agent', $event->context()['user_agent']);
    }

    public function test_event_captures_the_real_ip_of_the_request(): void
    {
        Event::fake([PluginSubmitted::class]);

        Sanctum::actingAs(User::factory()->create());

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('User-Agent', 'Plugin Review Agent')
            ->postJson('/api/v1/plugins', $this->payload())
            ->assertCreated();

        Event::assertDispatched(
            PluginSubmitted::class,
            fn (PluginSubmitted $event): bool => $event->ipAddress === '198.51.100.7'
                && $event->userAgent === 'Plugin Review Agent'
                && $event->context()['ip_address'] === '198.51.100.7'
                && $event->context()['user_agent'] === 'Plugin Review Agent',
        );
    }

    /**
     * The queued listener is executed by a worker, where the container's request
     * is the console request. The audit entry must describe the originating
     * request, not the one the worker happens to be running.
     */
    public function test_audit_context_survives_serialization_into_a_queue_worker(): void
    {
        $event = new PluginSubmitted(
            plugin: Plugin::factory()->create(),
            user: User::factory()->create(),
            ipAddress: '198.51.100.7',
            userAgent: 'Plugin Review Agent',
        );

        // Round-trip through the queue payload, then build context while a console
        // request is bound — exactly what CallQueuedListener does in the worker.
        $workerSideEvent = unserialize(serialize($event));

        $this->app->instance('request', Request::create('/'));

        $this->assertSame('198.51.100.7', $workerSideEvent->ipAddress);
        $this->assertSame('Plugin Review Agent', $workerSideEvent->userAgent);
        $this->assertSame('198.51.100.7', $workerSideEvent->context()['ip_address']);
        $this->assertSame('Plugin Review Agent', $workerSideEvent->context()['user_agent']);
    }

    public function test_event_context_does_not_fall_back_to_the_console_request(): void
    {
        $event = new PluginSubmitted(
            plugin: Plugin::factory()->create(),
            user: User::factory()->create(),
            ipAddress: '198.51.100.7',
            userAgent: 'Plugin Review Agent',
        );

        $this->app->instance('request', Request::create('/'));

        $context = $event->context();

        $this->assertNotSame('127.0.0.1', $context['ip_address']);
        $this->assertNotSame('Symfony', $context['user_agent']);
    }

    /**
     * Only the controller may read the HTTP request. The service must forward the
     * metadata it is given rather than resolving a request of its own, so it stays
     * usable from a console command or another non-HTTP caller.
     */
    public function test_plugin_service_forwards_the_request_metadata_it_receives(): void
    {
        Event::fake([PluginSubmitted::class]);

        $this->app->make(PluginService::class)->submit(
            User::factory()->create(),
            $this->payload(),
            '198.51.100.7',
            'Console Runner',
        );

        Event::assertDispatched(
            PluginSubmitted::class,
            fn (PluginSubmitted $event): bool => $event->ipAddress === '198.51.100.7'
                && $event->userAgent === 'Console Runner',
        );
    }

    public function test_plugin_service_needs_no_http_request_to_dispatch_events(): void
    {
        Event::fake([PluginSubmitted::class]);

        // Simulate a non-HTTP caller: the service must not depend on a request.
        $this->app->instance('request', Request::create('/'));

        $this->app->make(PluginService::class)->submit(
            User::factory()->create(),
            $this->payload(),
        );

        Event::assertDispatched(
            PluginSubmitted::class,
            fn (PluginSubmitted $event): bool => $event->ipAddress === null
                && $event->userAgent === null,
        );
    }

    public function test_invalid_submission_does_not_dispatch_plugin_submitted_event(): void
    {
        Event::fake([PluginSubmitted::class]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', [])
            ->assertUnprocessable();

        Event::assertNotDispatched(PluginSubmitted::class);
    }

    public function test_duplicate_submission_dispatches_plugin_submitted_event_only_once(): void
    {
        Event::fake([PluginSubmitted::class]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins', $this->payload())->assertCreated();
        $this->postJson('/api/v1/plugins', $this->payload())->assertUnprocessable();

        Event::assertDispatchedTimes(PluginSubmitted::class, 1);
    }

    public function test_plugin_submission_listener_is_queued_once(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/plugins', $this->payload())
            ->assertCreated();

        Bus::assertDispatchedTimes(CallQueuedListener::class, 1);

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(LogPluginSubmission::class, $job->class);
        $this->assertSame('handle', $job->method);
    }

    public function test_plugin_submission_is_logged_once_in_sync_test_environment(): void
    {
        $writes = 0;

        Log::listen(function () use (&$writes): void {
            $writes++;
        });

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/plugins', $this->payload())
            ->assertCreated();

        $this->assertSame(1, $writes);
    }
}
