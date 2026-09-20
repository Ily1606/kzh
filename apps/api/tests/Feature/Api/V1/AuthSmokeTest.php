<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSmokeTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonPath('user.id', $user->id);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->postJson('/api/v1/logout')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

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
