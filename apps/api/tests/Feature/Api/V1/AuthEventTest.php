<?php

namespace Tests\Feature\Api\V1;

use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Models\User;
use App\Services\AuthService;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_dispatches_user_registered_event(): void
    {
        Event::fake([UserRegistered::class]);

        $this->postJson('/api/v1/register', [
            'name' => 'Nguyen Van A',
            'email' => 'nguyen@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        Event::assertDispatched(UserRegistered::class, function (UserRegistered $event): bool {
            return $event->user->email === 'nguyen@example.com'
                && $event->context()['email'] === 'ngu***************'
                && $event->context()['user_id'] === $event->user->getKey();
        });
    }

    public function test_login_dispatches_user_logged_in_event_with_token_id(): void
    {
        Event::fake([UserLoggedIn::class]);

        $user = User::factory()->create([
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk();

        Event::assertDispatched(UserLoggedIn::class, function (UserLoggedIn $event) use ($user): bool {
            return $event->user->is($user)
                && $event->tokenId !== ''
                && $event->context()['token_id'] === $event->tokenId;
        });
    }

    public function test_login_does_not_dispatch_event_for_invalid_credentials(): void
    {
        Event::fake([UserLoggedIn::class]);

        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        Event::assertNotDispatched(UserLoggedIn::class);
    }

    public function test_logout_dispatches_user_logged_out_event_with_revoked_token_count(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
        ]);

        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->json('data.token');

        Event::fake([UserLoggedOut::class]);

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk();

        Event::assertDispatched(UserLoggedOut::class, function (UserLoggedOut $event) use ($user): bool {
            return $event->user->is($user)
                && $event->revokedTokensCount === 1
                && $event->context()['revoked_tokens'] === 1;
        });
    }

    public function test_auth_events_are_logged_by_the_default_listener(): void
    {
        $writes = 0;

        Log::listen(function () use (&$writes): void {
            $writes++;
        });

        $this->postJson('/api/v1/login', [
            'email' => User::factory()->create(['password' => 'secret-password'])->email,
            'password' => 'secret-password',
        ])->assertOk();

        // QUEUE_CONNECTION=sync in the test environment, so the queued listener
        // runs inline and must write the audit entry exactly once.
        $this->assertSame(1, $writes);
    }

    public function test_auth_events_capture_the_ip_and_user_agent_of_the_request(): void
    {
        Event::fake([UserLoggedIn::class]);

        $user = User::factory()->create(['password' => 'secret-password']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('User-Agent', 'Audit Agent')
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])
            ->assertOk();

        Event::assertDispatched(UserLoggedIn::class, function (UserLoggedIn $event): bool {
            return $event->requestContext->ipAddress === '198.51.100.7'
                && $event->requestContext->userAgent === 'Audit Agent'
                && $event->context()['ip_address'] === '198.51.100.7'
                && $event->context()['user_agent'] === 'Audit Agent';
        });
    }

    /**
     * The queued listener runs in a worker, where the bound request is the
     * console request (127.0.0.1 / "Symfony"). The context snapshotted at
     * construction time has to survive the queue payload untouched and must
     * never be rebuilt from the request the worker happens to see.
     */
    public function test_auth_event_context_stays_the_origin_request_after_the_queue_round_trip(): void
    {
        $user = User::factory()->create();
        $requestContext = new RequestContext('198.51.100.7', 'Audit Agent');

        $events = [
            new UserRegistered($user, $requestContext),
            new UserLoggedIn($user, 'token-id', $requestContext),
            new UserLoggedOut($user, $requestContext, 1),
        ];

        // A console request answers 127.0.0.1 / "Symfony"; binding it makes the
        // fallback this test guards against observable.
        $consoleRequest = Request::create('/');
        $this->app->instance('request', $consoleRequest);

        $this->assertSame('127.0.0.1', $consoleRequest->ip());
        $this->assertSame('Symfony', $consoleRequest->userAgent());

        foreach ($events as $event) {
            // Round-trip through the queue payload, exactly what
            // CallQueuedListener does before it invokes the listener.
            $workerSideEvent = unserialize(serialize($event));

            $this->assertSame('198.51.100.7', $workerSideEvent->requestContext->ipAddress, $event::class);
            $this->assertSame('Audit Agent', $workerSideEvent->requestContext->userAgent, $event::class);
            $this->assertSame('198.51.100.7', $workerSideEvent->context()['ip_address'], $event::class);
            $this->assertSame('Audit Agent', $workerSideEvent->context()['user_agent'], $event::class);
        }
    }

    /**
     * SerializesModels re-queries the user in the worker, so the audit entry
     * must not read the address off the restored row. A user who changes their
     * email before the job runs (the audit retries after 10s/60s) would
     * otherwise have the new address logged against the sign-in that used the
     * old one.
     */
    public function test_the_audit_context_reports_the_email_of_the_sign_in_not_the_current_one(): void
    {
        $user = User::factory()->create(['email' => 'nguyen@example.com']);
        $requestContext = new RequestContext('198.51.100.7', 'Audit Agent');

        $events = [
            new UserRegistered($user, $requestContext),
            new UserLoggedIn($user, 'token-id', $requestContext),
            new UserLoggedOut($user, $requestContext, 1),
        ];

        $user->forceFill(['email' => 'changed@example.com'])->save();

        foreach ($events as $event) {
            // Round-trip through the queue payload, exactly what
            // CallQueuedListener does before it invokes the listener.
            $workerSideEvent = unserialize(serialize($event));

            $this->assertSame('ngu***************', $workerSideEvent->context()['email'], $event::class);
            $this->assertSame($user->getKey(), $workerSideEvent->context()['user_id'], $event::class);
        }
    }

    /**
     * Only the controller may read the HTTP request. The service must forward the
     * metadata it is given rather than resolving a request of its own, so it stays
     * usable from a console command or another non-HTTP caller.
     */
    public function test_auth_service_forwards_the_request_metadata_it_receives(): void
    {
        Event::fake([UserRegistered::class]);

        $this->app->make(AuthService::class)->register([
            'name' => 'Service Metadata',
            'email' => 'service-metadata@example.com',
            'password' => 'secret-password',
        ], new RequestContext('198.51.100.7', 'Console Runner'));

        Event::assertDispatched(
            UserRegistered::class,
            fn (UserRegistered $event): bool => $event->requestContext->ipAddress === '198.51.100.7'
                && $event->requestContext->userAgent === 'Console Runner',
        );
    }

    /**
     * A non-HTTP caller must opt in to missing metadata explicitly. The context
     * itself is still required so a forgotten argument fails fast instead of
     * silently dropping the audit metadata.
     */
    public function test_auth_service_needs_no_http_request_to_dispatch_events(): void
    {
        Event::fake([UserRegistered::class]);

        // Simulate a non-HTTP caller: the service must not depend on a request.
        $this->app->instance('request', Request::create('/'));

        $this->app->make(AuthService::class)->register([
            'name' => 'No Http',
            'email' => 'no-http@example.com',
            'password' => 'secret-password',
        ], new RequestContext(null, null));

        Event::assertDispatched(
            UserRegistered::class,
            fn (UserRegistered $event): bool => $event->requestContext->ipAddress === null
                && $event->requestContext->userAgent === null,
        );
    }
}
