<?php

namespace Tests\Feature;

use App\Mail\EmailChangeAlertMail;
use App\Mail\EmailChangeVerifyMail;
use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_user_can_request_email_change()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/email/request', [
            'current_password' => 'password123',
            'new_email' => 'new@example.com',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('email_change_requests', [
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
        ]);

        Mail::assertSent(EmailChangeVerifyMail::class, function ($mail) {
            return $mail->hasTo('new@example.com');
        });

        Mail::assertSent(EmailChangeAlertMail::class, function ($mail) {
            return $mail->hasTo('old@example.com');
        });
    }

    public function test_user_cannot_request_email_change_with_wrong_password()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/email/request', [
            'current_password' => 'wrongpassword',
            'new_email' => 'new@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertDatabaseMissing('email_change_requests', [
            'user_id' => $user->id,
        ]);
        
        Mail::assertNothingSent();
    }

    public function test_user_cannot_request_email_change_with_existing_email()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        User::factory()->create([
            'email' => 'taken@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/email/request', [
            'current_password' => 'password123',
            'new_email' => 'taken@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('new_email');
    }

    public function test_user_cannot_request_email_change_with_current_email()
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/email/request', [
            'current_password' => 'password123',
            'new_email' => 'current@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('new_email');
    }

    public function test_requesting_email_change_twice_overwrites_old_request()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/email/request', [
            'current_password' => 'password123',
            'new_email' => 'first@example.com',
        ]);

        $this->postJson('/api/v1/user/email/request', [
            'current_password' => 'password123',
            'new_email' => 'second@example.com',
        ]);

        $this->assertDatabaseCount('email_change_requests', 1);
        $this->assertDatabaseHas('email_change_requests', [
            'user_id' => $user->id,
            'new_email' => 'second@example.com',
        ]);
    }

    public function test_user_can_verify_email_change_with_correct_token()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        $token = 'random_64_character_token_string_here_1234567890_abcdefghijklmnop';

        EmailChangeRequest::create([
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
            'token' => $token,
            'expires_at' => now()->addMinutes(30),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/email/verify', [
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
        ]);

        $this->assertDatabaseMissing('email_change_requests', [
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_verify_with_wrong_token()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        EmailChangeRequest::create([
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
            'token' => 'correct_token',
            'expires_at' => now()->addMinutes(30),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/email/verify', [
            'token' => 'wrong_token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('token');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
        ]);
    }

    public function test_user_cannot_verify_with_expired_token()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        EmailChangeRequest::create([
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
            'token' => 'correct_token',
            'expires_at' => now()->subMinutes(1), // Expired
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/email/verify', [
            'token' => 'correct_token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('token');
    }

    public function test_tokens_are_revoked_on_email_change()
    {
        $user = User::factory()->create();

        EmailChangeRequest::create([
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
            'token' => 'valid_token',
            'expires_at' => now()->addMinutes(30),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/email/verify', ['token' => 'valid_token'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    }
}
