<?php

namespace App\Services;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    /**
     * Send a password reset link to the given user.
     *
     * @throws ValidationException
     */
    public function sendResetLink(array $credentials): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });

        $status = Password::sendResetLink($credentials);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Reset the user's password.
     *
     * @throws ValidationException
     */
    public function resetPassword(array $credentials): void
    {
        $status = Password::reset(
            $credentials,
            function ($user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }
}
