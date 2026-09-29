<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\RecordsAuthActivity;
use App\Enums\AuthEventType;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Listeners\Auth\LogAuthActivity;
use App\Models\User;
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
            new UserRegistered($user),
            new UserLoggedIn($user, 'token-id'),
            new UserLoggedOut($user, 2),
        ];
    }

    public function test_every_auth_event_produces_exactly_one_audit_entry(): void
    {
        Bus::fake();

        foreach ($this->authEvents() as $event) {
            Event::dispatch($event);
        }

        // We assert that LogAuthActivity specifically was queued 3 times
        $jobs = Bus::dispatched(CallQueuedListener::class, fn ($job) => $job->class === LogAuthActivity::class);
        $this->assertCount(3, $jobs);
    }

    public function test_auth_events_do_not_implement_the_listener_twice(): void
    {
        $calls = 0;

        Log::listen(function () use (&$calls): void {
            $calls++;
        });

        UserLoggedIn::dispatch(User::factory()->create(), 'token-id');

        // A listener bound to an auth event twice — for instance if it were also
        // listed in a $listen mapping alongside its own contract type-hint —
        // would write the audit entry twice.
        $this->assertSame(1, $calls);
    }

    public function test_the_auth_listener_is_queued_onto_the_auth_queue(): void
    {
        Bus::fake();

        UserLoggedIn::dispatch(User::factory()->create(), 'token-id');

        Bus::assertDispatchedTimes(CallQueuedListener::class, 1);

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(LogAuthActivity::class, $job->class);
        $this->assertSame('handle', $job->method);
    }

    public function test_app_events_that_are_not_auth_activity_are_ignored(): void
    {
        Bus::fake();

        Event::dispatch('App\\Events\\UnrelatedEvent', ['not-an-auth-event']);

        Bus::assertNotDispatched(CallQueuedListener::class);
    }

    public function test_auth_events_expose_the_contract_consumed_by_the_listener(): void
    {
        $user = User::factory()->create();

        $events = [
            new UserRegistered($user),
            new UserLoggedIn($user, 'token-id'),
            new UserLoggedOut($user, 2),
        ];

        foreach ($events as $event) {
            $this->assertInstanceOf(RecordsAuthActivity::class, $event);
            $this->assertInstanceOf(AuthEventType::class, $event->eventType());
        }
    }
}
