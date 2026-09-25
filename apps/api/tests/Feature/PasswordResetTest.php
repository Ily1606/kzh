<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Auth\Notifications\ResetPassword;
use Carbon\Carbon;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reset_link_with_valid_email()
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'user@example.com'
        ]);

        $response->assertStatus(200);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_fails_if_email_does_not_exist()
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'notfound@example.com'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_email_is_missing()
    {
        $response = $this->postJson('/api/v1/forgot-password', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_email_is_empty()
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => ''
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_email_format_is_invalid()
    {
        $invalidEmails = ['abc', 'user@', '@example.com'];

        foreach ($invalidEmails as $email) {
            $response = $this->postJson('/api/v1/forgot-password', [
                'email' => $email
            ]);

            $response->assertStatus(422)
                     ->assertJsonValidationErrors(['email']);
        }
    }

    public function test_trims_whitespace_from_email()
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => '  user@example.com  '
        ]);

        $response->assertStatus(200);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_handles_case_insensitive_email()
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('SQLite string comparison is case-sensitive by default.');
        }

        Notification::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'USER@EXAMPLE.COM'
        ]);

        $response->assertStatus(200);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_throttles_reset_link_requests()
    {
        Notification::fake();
        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/v1/forgot-password', ['email' => 'user@example.com'])
             ->assertStatus(200);

        $this->postJson('/api/v1/forgot-password', ['email' => 'user@example.com'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_email_is_too_long()
    {
        $longEmail = Str::random(250) . '@example.com';
        
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $longEmail
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_resets_password_successfully()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('new_password123', $user->fresh()->password));
    }

    public function test_fails_if_token_does_not_exist()
    {
        User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'random-invalid-token',
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_token_is_expired()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'created_at' => Carbon::now()->subMinutes(config('auth.passwords.users.expire') + 1)
        ]);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_token_is_already_used()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ])->assertStatus(200);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'another_password123',
            'password_confirmation' => 'another_password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_email_is_random()
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'some-token',
            'email' => 'random@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_email_does_not_match_token()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'wrong@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_token_is_missing_or_empty()
    {
        $responseMissing = $this->postJson('/api/v1/reset-password', [
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);
        $responseMissing->assertStatus(422)->assertJsonValidationErrors(['token']);

        $responseEmpty = $this->postJson('/api/v1/reset-password', [
            'token' => '',
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);
        $responseEmpty->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_fails_if_email_is_missing_in_reset()
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'some-token',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_email_format_is_invalid_in_reset()
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'some-token',
            'email' => 'abc',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_fails_if_password_is_missing_or_empty()
    {
        $responseMissing = $this->postJson('/api/v1/reset-password', [
            'token' => 'some-token',
            'email' => 'user@example.com',
        ]);
        $responseMissing->assertStatus(422)->assertJsonValidationErrors(['password']);

        $responseEmpty = $this->postJson('/api/v1/reset-password', [
            'token' => 'some-token',
            'email' => 'user@example.com',
            'password' => '',
        ]);
        $responseEmpty->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_fails_if_password_is_too_short()
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'some-token',
            'email' => 'user@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_succeeds_with_8_character_password()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);
        $response->assertStatus(200);
    }

    public function test_fails_if_confirmation_is_missing()
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'some-token',
            'email' => 'user@example.com',
            'password' => '12345678',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_fails_if_confirmation_does_not_match()
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'some-token',
            'email' => 'user@example.com',
            'password' => '12345678',
            'password_confirmation' => '123456789',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_succeeds_with_special_characters_password()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'Abc@1234',
            'password_confirmation' => 'Abc@1234',
        ]);
        $response->assertStatus(200);
    }

    public function test_succeeds_with_unicode_password()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $unicodePassword = 'Mậtkhẩu@123';

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => $unicodePassword,
            'password_confirmation' => $unicodePassword,
        ]);
        
        $response->assertStatus(200);
        $this->assertTrue(Hash::check($unicodePassword, $user->fresh()->password));
    }

    public function test_handles_very_long_password()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $longPassword = Str::random(200);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => $longPassword,
            'password_confirmation' => $longPassword,
        ]);
        
        $response->assertStatus(200);
        $this->assertTrue(Hash::check($longPassword, $user->fresh()->password));
    }

    public function test_allows_new_password_same_as_old()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('same_password123')
        ]);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'same_password123',
            'password_confirmation' => 'same_password123',
        ]);
        
        $response->assertStatus(200);
    }

    public function test_hashes_password_on_reset()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ])->assertStatus(200);

        $dbPassword = $user->fresh()->password;
        $this->assertNotEquals('new_password123', $dbPassword);
        $this->assertTrue(Hash::check('new_password123', $dbPassword));
    }

    public function test_login_works_with_new_password_and_fails_with_old()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old_password'),
            'is_active' => true,
            'is_deleted' => false,
        ]);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ])->assertStatus(200);

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'old_password',
        ])->assertStatus(422);
        
        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'new_password123',
        ])->assertStatus(200);
    }

    public function test_revokes_all_tokens_on_reset()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);
        // Giả lập user đang đăng nhập và có 1 token
        $user->createToken('test-token');
        $this->assertCount(1, $user->tokens);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ])->assertStatus(200);

        // Sau khi reset, toàn bộ token bị xóa (đăng xuất mọi thiết bị)
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_works_without_authentication()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_token_is_not_exposed_in_api_response()
    {
        Notification::fake();
        User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'user@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertJsonMissing(['token']);
        $this->assertNull($response->json('data'));
    }

    public function test_reset_link_uses_frontend_url_and_encoded_email()
    {
        Notification::fake();
        config()->set('app.frontend_url', 'https://my-vue-app.com');
        
        $user = User::factory()->create(['email' => 'test+1@example.com']);

        $this->postJson('/api/v1/forgot-password', [
            'email' => 'test+1@example.com'
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification, $channels) use ($user) {
            $mail = $notification->toMail($user);
            $actionUrl = $mail->actionUrl;

            $expectedUrlPrefix = 'https://my-vue-app.com/reset-password?token=';
            $expectedEmailParam = '&email=' . urlencode($user->email);

            return str_starts_with($actionUrl, $expectedUrlPrefix) && str_ends_with($actionUrl, $expectedEmailParam);
        });
    }

    public function test_cannot_reset_other_users_password_with_mismatched_token()
    {
        $userA = User::factory()->create(['email' => 'usera@example.com']);
        $userB = User::factory()->create(['email' => 'userb@example.com']);
        
        $tokenA = Password::createToken($userA);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $tokenA,
            'email' => 'userb@example.com',
            'password' => 'hacked_password123',
            'password_confirmation' => 'hacked_password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
        
        $this->assertFalse(Hash::check('hacked_password123', $userA->fresh()->password));
        $this->assertFalse(Hash::check('hacked_password123', $userB->fresh()->password));
    }
}
