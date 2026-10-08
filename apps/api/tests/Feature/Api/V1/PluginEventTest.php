<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Events\Plugin\PluginSubmitted;
use App\Listeners\Plugin\LogPluginSubmission;
use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginService;
use App\Support\RequestContext;
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
            'category' => 'Developer tools',
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
            fn (PluginSubmitted $event): bool => $event->requestContext->ipAddress === '198.51.100.7'
                && $event->requestContext->userAgent === 'Plugin Review Agent'
                && $event->context()['ip_address'] === '198.51.100.7'
                && $event->context()['user_agent'] === 'Plugin Review Agent',
        );
    }

    /**
     * The queued listener runs in a worker, where the bound request is the
     * console request (127.0.0.1 / "Symfony"). The context snapshotted at
     * construction time has to survive the queue payload untouched and must
     * never be rebuilt from the request the worker happens to see.
     */
    public function test_event_context_stays_the_origin_request_after_the_queue_round_trip(): void
    {
        $event = new PluginSubmitted(
            plugin: Plugin::factory()->create(),
            user: User::factory()->create(),
            requestContext: new RequestContext('198.51.100.7', 'Plugin Review Agent'),
        );

        // A console request answers 127.0.0.1 / "Symfony"; binding it makes the
        // fallback this test guards against observable.
        $consoleRequest = Request::create('/');
        $this->app->instance('request', $consoleRequest);

        $this->assertSame('127.0.0.1', $consoleRequest->ip());
        $this->assertSame('Symfony', $consoleRequest->userAgent());

        // Round-trip through the queue payload, exactly what CallQueuedListener
        // does before it invokes the listener.
        $workerSideEvent = unserialize(serialize($event));

        $this->assertSame('198.51.100.7', $workerSideEvent->requestContext->ipAddress);
        $this->assertSame('Plugin Review Agent', $workerSideEvent->requestContext->userAgent);
        $this->assertSame('198.51.100.7', $workerSideEvent->context()['ip_address']);
        $this->assertSame('Plugin Review Agent', $workerSideEvent->context()['user_agent']);
    }

    /**
     * SerializesModels re-queries the models in the worker, so context() must
     * not read the submission off the restored row. An admin approval between
     * dispatch and the run (the audit retries after 10s/60s) would otherwise
     * land a "Plugin submitted." entry carrying `status: approved`, which
     * records a decision the event never described.
     */
    public function test_the_audit_context_reports_the_status_of_the_submission_not_the_current_one(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);
        $user = User::factory()->create();

        $event = new PluginSubmitted(
            plugin: $plugin,
            user: $user,
            requestContext: new RequestContext('198.51.100.7', 'Plugin Review Agent'),
        );

        $plugin->forceFill(['status' => PluginStatus::Approved])->save();

        // Round-trip through the queue payload, exactly what CallQueuedListener
        // does before it invokes the listener.
        $workerSideEvent = unserialize(serialize($event));

        $this->assertSame('approved', $plugin->fresh()->status->value);
        $this->assertSame('pending', $workerSideEvent->context()['status']);
        $this->assertSame($plugin->getKey(), $workerSideEvent->context()['plugin_id']);
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
            new RequestContext('198.51.100.7', 'Console Runner'),
        );

        Event::assertDispatched(
            PluginSubmitted::class,
            fn (PluginSubmitted $event): bool => $event->requestContext->ipAddress === '198.51.100.7'
                && $event->requestContext->userAgent === 'Console Runner',
        );
    }

    /**
     * A non-HTTP caller must opt in to missing metadata explicitly. The context
     * itself is still required so a forgotten argument fails fast instead of
     * silently dropping the audit metadata.
     */
    public function test_plugin_service_needs_no_http_request_to_dispatch_events(): void
    {
        Event::fake([PluginSubmitted::class]);

        // Simulate a non-HTTP caller: the service must not depend on a request.
        $this->app->instance('request', Request::create('/'));

        $this->app->make(PluginService::class)->submit(
            User::factory()->create(),
            $this->payload(),
            new RequestContext(null, null),
        );

        Event::assertDispatched(
            PluginSubmitted::class,
            fn (PluginSubmitted $event): bool => $event->requestContext->ipAddress === null
                && $event->requestContext->userAgent === null,
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
