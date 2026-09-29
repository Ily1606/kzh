<?php

namespace Tests\Feature\Api\V1;

use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Models\User;
use App\Services\AuthService;
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
                && $event->context()['email'] === 'nguyen@example.com'
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
            return $event->ipAddress === '198.51.100.7'
                && $event->userAgent === 'Audit Agent'
                && $event->context()['ip_address'] === '198.51.100.7'
                && $event->context()['user_agent'] === 'Audit Agent';
        });
    }

    /**
     * The queued listener is executed by a worker, where app(Request::class) is the
     * console request. The audit entry must describe the originating request, not
     * the one the worker happens to be running.
     */
    public function test_audit_context_survives_serialization_into_a_queue_worker(): void
    {
        $event = new UserRegistered(
            user: User::factory()->create(),
            ipAddress: '198.51.100.7',
            userAgent: 'Audit Agent',
        );

        // Round-trip through the queue payload, then build context while a console
        // request is bound — exactly what CallQueuedListener does in the worker.
        $workerSideEvent = unserialize(serialize($event));

        $this->app->instance('request', Request::create('/'));

        $this->assertSame('198.51.100.7', $workerSideEvent->ipAddress);
        $this->assertSame('Audit Agent', $workerSideEvent->userAgent);
        $this->assertSame('198.51.100.7', $workerSideEvent->context()['ip_address']);
        $this->assertSame('Audit Agent', $workerSideEvent->context()['user_agent']);
    }

    public function test_auth_event_context_does_not_fall_back_to_the_console_request(): void
    {
        $event = new UserLoggedOut(
            user: User::factory()->create(),
            ipAddress: '198.51.100.7',
            userAgent: 'Audit Agent',
            revokedTokensCount: 1,
        );

        $this->app->instance('request', Request::create('/'));

        $context = $event->context();

        $this->assertNotSame('127.0.0.1', $context['ip_address']);
        $this->assertNotSame('Symfony', $context['user_agent']);
    }

    public function test_auth_event_constructor_preserves_explicit_request_metadata(): void
    {
        $event = new UserRegistered(
            user: User::factory()->make(),
            ipAddress: '192.0.2.10',
            userAgent: 'Console Runner',
        );

        $this->assertSame('192.0.2.10', $event->ipAddress);
        $this->assertSame('192.0.2.10', $event->context()['ip_address']);
        $this->assertSame('Console Runner', $event->userAgent);
        $this->assertSame('Console Runner', $event->context()['user_agent']);
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
        ], '198.51.100.7', 'Console Runner');

        Event::assertDispatched(
            UserRegistered::class,
            fn (UserRegistered $event): bool => $event->ipAddress === '198.51.100.7'
                && $event->userAgent === 'Console Runner',
        );
    }

    public function test_auth_service_needs_no_http_request_to_dispatch_events(): void
    {
        Event::fake([UserRegistered::class]);

        // Simulate a non-HTTP caller: the service must not depend on a request.
        $this->app->instance('request', Request::create('/'));

        $this->app->make(AuthService::class)->register([
            'name' => 'No Http',
            'email' => 'no-http@example.com',
            'password' => 'secret-password',
        ]);

        Event::assertDispatched(
            UserRegistered::class,
            fn (UserRegistered $event): bool => $event->ipAddress === null
                && $event->userAgent === null,
        );
    }
}
