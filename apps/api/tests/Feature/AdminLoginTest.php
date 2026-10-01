<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Filament shows the exact same message whether the password is wrong or the
     * account is not allowed into the panel, so the form never reveals which
     * emails exist. Both failure assertions below rely on that shared message.
     */
    private function failedMessage(): string
    {
        return __('filament-panels::auth/pages/login.messages.failed');
    }

    public function test_admin_with_valid_credentials_can_log_in_to_the_admin_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_regular_user_cannot_log_in_to_the_admin_panel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => $this->failedMessage()]);

        $this->assertGuest();
    }

    public function test_login_fails_when_the_password_is_wrong(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'not-the-right-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => $this->failedMessage()]);

        $this->assertGuest();
    }

    public function test_login_fails_when_the_email_does_not_exist(): void
    {
        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'nobody@example.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => $this->failedMessage()]);

        $this->assertGuest();
    }

    public function test_a_regular_user_and_a_wrong_password_are_indistinguishable_to_the_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $nonAdminAttempt = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate');

        $wrongPasswordAttempt = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'not-the-right-password'])
            ->call('authenticate');

        $this->assertSame(
            $nonAdminAttempt->errors()->get('data.email'),
            $wrongPasswordAttempt->errors()->get('data.email'),
        );
    }

    public function test_locked_admin_cannot_log_in_to_the_admin_panel(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'locked_at' => now(),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => $this->failedMessage()]);

        $this->assertGuest();
    }

    public function test_soft_deleted_admin_cannot_log_in_to_the_admin_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->delete();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => $this->failedMessage()]);

        $this->assertGuest();
    }
}
