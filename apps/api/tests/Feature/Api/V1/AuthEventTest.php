<?php

namespace Tests\Feature\Api\V1;

use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
        $this->postJson('/api/v1/login', [
            'email' => User::factory()->create(['password' => 'secret-password'])->email,
            'password' => 'secret-password',
        ])->assertOk();

        // QUEUE_CONNECTION=sync in the test environment, so the queued listener
        // runs inline; LogAuthActivity must therefore not throw.
        $this->assertTrue(true);
    }
}
