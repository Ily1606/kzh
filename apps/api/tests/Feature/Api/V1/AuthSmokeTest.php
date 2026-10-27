<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_token(): void
    {
        $response = $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/register', [
                'name' => 'Nguyen Van A',
                'email' => 'nguyen@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.name', 'Nguyen Van A')
            ->assertJsonPath('data.user.email', 'nguyen@example.com')
            ->assertJsonPath('data.token', fn (string $token): bool => $token !== '')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertJsonPath('success', true)
            ->assertJsonPath('errors', null);

        $this->assertDatabaseHas('users', [
            'email' => 'nguyen@example.com',
            'locked_at' => null,
            'is_admin' => false,
            'deleted_at' => null,
        ]);
        $this->assertGuest('web');
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $this->postJson('/api/v1/register', [
            'name' => 'Another User',
            'email' => $user->email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_duplicate_email_case_insensitively(): void
    {
        // Existing user has uppercase in email (pre-normalization)
        User::factory()->create([
            'email' => 'LegacyUser@Example.Com',
        ]);

        $this->postJson('/api/v1/register', [
            'name' => 'Another User',
            'email' => 'legacyuser@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_register_allows_same_email_if_previous_account_was_soft_deleted(): void
    {
        $oldUser = User::factory()->create([
            'email' => 'reuse@example.com',
            'password' => 'old-password',
            'deleted_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'New User',
            'email' => 'reuse@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertCreated();
        $newUserId = $response->json('data.user.id');

        $this->assertNotEquals($oldUser->id, $newUserId);

        // Verify that the new user can log in with new password
        $this->postJson('/api/v1/login', [
            'email' => 'reuse@example.com',
            'password' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $newUserId);
    }

    public function test_register_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Nguyen Van A',
            'email' => 'nguyen@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_user_can_login_fetch_their_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
        ]);

        $loginResponse = $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])->assertOk();

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Logout successful.',
                'data' => null,
                'errors' => null,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_profile_and_logout_require_authentication(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
        $this->postJson('/api/v1/logout')->assertUnauthorized();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Email or password is incorrect.')
            ->assertJsonPath('errors.email.0', 'Email or password is incorrect.')
            ->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_inactive_user(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
            'locked_at' => now(),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Your account has been locked. Please contact support.')
            ->assertJsonPath('errors.email.0', 'Your account has been locked. Please contact support.')
            ->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_deleted_user(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
            'deleted_at' => now(),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'This account has been deleted.')
            ->assertJsonPath('errors.email.0', 'This account has been deleted.')
            ->assertJsonValidationErrors('email');
    }

    public function test_login_does_not_reveal_locked_or_deleted_status_when_password_is_wrong(): void
    {
        $lockedUser = User::factory()->create([
            'password' => 'secret-password',
            'locked_at' => now(),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $lockedUser->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Email or password is incorrect.')
            ->assertJsonPath('errors.email.0', 'Email or password is incorrect.');

        $deletedUser = User::factory()->create([
            'password' => 'secret-password',
            'deleted_at' => now(),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $deletedUser->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Email or password is incorrect.')
            ->assertJsonPath('errors.email.0', 'Email or password is incorrect.');
    }

    public function test_auth_endpoints_are_rate_limited_per_ip(): void
    {
        $limit = (int) config('rate_limits.auth_per_minute');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->postJson('/api/v1/login', [
                'email' => 'missing@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_auth_rate_limit_is_configurable_per_environment(): void
    {
        config()->set('rate_limits.auth_per_minute', 1);

        $this->postJson('/api/v1/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_vue_origin_can_make_credentialed_cors_requests(): void
    {
        config()->set('cors.allowed_origins', ['http://localhost:5173']);

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_login_returns_429_when_rate_limit_exceeded(): void
    {
        $user = User::factory()->create();

        $limit = (int) config('auth.limiters.login_per_email', 5);

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // The next time should be rate limited (429 Too Many Requests)
        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    /**
     * Test: Attacker (IP 2) tries to brute-force Victim (IP 1).
     * Attacker hits the email|ip limit (5) and gets 429.
     * The account limit is 30, so Victim's login counter is only at 5.
     * Victim logs in correctly from their own IP, which succeeds (200 OK) and clears the account counter.
     */
    public function test_login_from_victim_ip_succeeds_even_when_attacker_hits_ip_limit(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $limit = (int) config('auth.limiters.login_per_email');

        // Attacker uses IP 2.2.2.2 and hits the email|ip limit.
        for ($i = 0; $i < $limit; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])
                ->postJson('/api/v1/login', [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ]);
        }

        // Attacker gets 429 from the middleware because they hit the email|ip limit
        $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(429);

        // Victim logs in from their normal IP 1.1.1.1
        // Expected: 200 OK because the global account limit (30) is not reached.
        $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])->assertOk();
    }
}
