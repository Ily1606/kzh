<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_update_password()
    {
        $response = $this->patchJson('/api/v1/user/password', [
            'current_password' => 'password',
            'new_password' => 'new_password123',
            'new_password_confirmation' => 'new_password123',
        ]);
        $response->assertStatus(401);
    }

    public function test_user_can_update_password_successfully()
    {
        $user = User::factory()->create(['password' => Hash::make('old_password')]);

        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user/password', [
            'current_password' => 'old_password',
            'new_password' => 'new_password123',
            'new_password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('new_password123', $user->fresh()->password));
    }

    public function test_validation_fails_if_current_password_is_incorrect()
    {
        $user = User::factory()->create(['password' => Hash::make('old_password')]);

        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user/password', [
            'current_password' => 'wrong_password',
            'new_password' => 'new_password123',
            'new_password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['current_password']);
    }

    public function test_validation_fails_if_current_password_is_missing()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user/password', [
            'new_password' => 'new_password123',
            'new_password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['current_password']);
    }

    public function test_validation_fails_if_new_password_is_missing()
    {
        $user = User::factory()->create(['password' => Hash::make('old_password')]);

        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user/password', [
            'current_password' => 'old_password',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['new_password']);
    }

    public function test_validation_fails_if_new_password_is_too_short()
    {
        $user = User::factory()->create(['password' => Hash::make('old_password')]);

        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user/password', [
            'current_password' => 'old_password',
            'new_password' => '1234567',
            'new_password_confirmation' => '1234567',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['new_password']);
    }

    public function test_user_can_update_password_with_exactly_8_characters()
    {
        $user = User::factory()->create(['password' => Hash::make('old_password')]);

        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user/password', [
            'current_password' => 'old_password',
            'new_password' => '12345678',
            'new_password_confirmation' => '12345678',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('12345678', $user->fresh()->password));
    }

    public function test_validation_fails_if_password_confirmation_is_missing()
    {
        $user = User::factory()->create(['password' => Hash::make('old_password')]);

        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user/password', [
            'current_password' => 'old_password',
            'new_password' => 'new_password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['new_password']);
    }

    public function test_validation_fails_if_password_confirmation_does_not_match()
    {
        $user = User::factory()->create(['password' => Hash::make('old_password')]);

        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user/password', [
            'current_password' => 'old_password',
            'new_password' => '12345678',
            'new_password_confirmation' => '123456789',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['new_password']);
    }

    public function test_user_can_login_with_new_password_and_fails_with_old_password()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old_password'),
            'is_active' => true,
            'is_deleted' => false,
        ]);

        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/user/password', [
            'current_password' => 'old_password',
            'new_password' => 'new_password123',
            'new_password_confirmation' => 'new_password123',
        ])->assertStatus(200);

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/v1/logout');
        Auth::forgetGuards();
        Auth::shouldUse('web');

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'old_password',
        ])->assertStatus(422);

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'new_password123',
        ])->assertStatus(200);
    }
}
