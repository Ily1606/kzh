<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_is_logged_in(): void
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
            ->assertJsonPath('data.name', 'Nguyen Van A')
            ->assertJsonPath('data.email', 'nguyen@example.com')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonPath('success', true)
            ->assertJsonPath('errors', null);

        $this->assertDatabaseHas('users', [
            'email' => 'nguyen@example.com',
            'isActive' => true,
            'is_admin' => false,
            'isDeleted' => false,
        ]);
        $this->assertAuthenticated('web');
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

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->postJson('/api/v1/logout')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Logout successful.',
                'data' => null,
                'errors' => null,
            ]);

        $this->assertGuest('web');
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
            'isActive' => false,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_deleted_user(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
            'isDeleted' => true,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
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
}
