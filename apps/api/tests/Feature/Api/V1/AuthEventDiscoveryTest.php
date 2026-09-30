<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\RecordsAuthActivity;
use App\Enums\AuthEventType;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Events\Plugin\PaginatedPluginsFetched;
use App\Listeners\Auth\LogAuthActivity;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Guards how Laravel's event discovery wires auth events to their listeners.
 *
 * Auth events are consumed through App\Contracts\RecordsAuthActivity rather than
 * a per-event mapping, so these tests pin the guarantees that make that safe:
 * every auth event is handled, exactly once, and unrelated App\Events payloads
 * never reach the auth listeners. No EventServiceProvider is involved — Laravel
 * scans app/Listeners and binds LogAuthActivity through the interface type-hint
 * of its handle() method.
 */
class AuthEventDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Auth events use SerializesModels, so they must carry a persisted user:
     * an unsaved `new User` has no key and the queued listener cannot
     * re-fetch it (ModelNotFoundException / missing table on sqlite).
     *
     * @return array<int, RecordsAuthActivity>
     */
    private function authEvents(): array
    {
        $user = User::factory()->create();

        return [
            new UserRegistered($user, new RequestContext(null, null)),
            new UserLoggedIn($user, 'token-id', new RequestContext(null, null)),
            new UserLoggedOut($user, new RequestContext(null, null), 2),
        ];
    }

    /**
     * Auth events must compose their payload from the one shared base context.
     *
     * Guards the DRY contract: a field added to the trait (e.g. request_id) has
     * to reach every auth event, and no event may rebuild the shared block by
     * hand.
     */
    public function test_every_auth_event_shares_the_same_base_context(): void
    {
        $user = User::factory()->create(['email' => 'nguyen@example.com']);
        $requestContext = new RequestContext('198.51.100.7', 'Audit Agent');

        $events = [
            new UserRegistered($user, $requestContext),
            new UserLoggedIn($user, 'token-id', $requestContext),
            new UserLoggedOut($user, $requestContext, 2),
        ];

        $expectedBase = [
            'user_id' => $user->getKey(),
            'email' => 'ngu***************',
            'ip_address' => '198.51.100.7',
            'user_agent' => 'Audit Agent',
        ];

        foreach ($events as $event) {
            $this->assertSame($expectedBase, $event->baseContext(), $event::class);
            $this->assertSame($expectedBase, array_intersect_key($event->context(), $expectedBase), $event::class);
            $this->assertSame('198.51.100.7', $event->ipAddress(), $event::class);
        }
    }

    public function test_every_auth_event_produces_exactly_one_audit_entry(): void
    {
        Bus::fake();

        foreach ($this->authEvents() as $event) {
            Event::dispatch($event);
        }

        // One queued audit job per event: a missed or duplicated binding shows
        // up as a count other than 3.
        $this->assertCount(
            3,
            Bus::dispatched(CallQueuedListener::class)
                ->filter(fn (CallQueuedListener $job): bool => $job->class === LogAuthActivity::class),
        );
    }

    public function test_auth_events_do_not_implement_the_listener_twice(): void
    {
        $calls = 0;

        Log::listen(function () use (&$calls): void {
            $calls++;
        });

        UserLoggedIn::dispatch(User::factory()->create(), 'token-id', new RequestContext(null, null));

        // A listener bound to an auth event twice — for instance if it were also
        // listed in a $listen mapping alongside its own contract type-hint —
        // would write the audit entry twice.
        $this->assertSame(1, $calls);
    }

    public function test_the_auth_listener_is_queued_onto_the_auth_queue(): void
    {
        Bus::fake();

        UserLoggedIn::dispatch(User::factory()->create(), 'token-id', new RequestContext(null, null));

        Bus::assertDispatchedTimes(CallQueuedListener::class, 1);

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(LogAuthActivity::class, $job->class);
        $this->assertSame('handle', $job->method);
    }

    /**
     * The audit listener is bound through the RecordsAuthActivity interface of
     * its handle() type-hint, so an unrelated App\Events payload has no binding
     * that could reach it. The auth event dispatched first is a positive
     * control: it proves the count below observes real queued audit jobs
     * instead of a silently empty bus.
     */
    public function test_app_events_that_are_not_auth_activity_are_ignored(): void
    {
        Bus::fake();

        UserLoggedIn::dispatch(User::factory()->create(), 'token-id', new RequestContext(null, null));

        $auditJobs = fn () => Bus::dispatched(CallQueuedListener::class)
            ->filter(fn (CallQueuedListener $job): bool => $job->class === LogAuthActivity::class);

        $this->assertCount(1, $auditJobs());

        // A real App\Events payload that is not auth activity must add nothing:
        // a wildcard (App\Events\*) or an over-broad registration would.
        Event::dispatch(new PaginatedPluginsFetched(15, 100, 2, '198.51.100.7', 'Unrelated Agent'));

        $this->assertCount(1, $auditJobs());
    }

    public function test_auth_events_expose_the_contract_consumed_by_the_listener(): void
    {
        $user = User::factory()->create();

        $events = [
            new UserRegistered($user, new RequestContext(null, null)),
            new UserLoggedIn($user, 'token-id', new RequestContext(null, null)),
            new UserLoggedOut($user, new RequestContext(null, null), 2),
        ];

        foreach ($events as $event) {
            $this->assertInstanceOf(RecordsAuthActivity::class, $event);
            $this->assertInstanceOf(AuthEventType::class, $event->eventType());
        }
    }
}
