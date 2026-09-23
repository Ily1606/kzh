<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return ApiResponse::successResponse(null, __($status));
        }

        return ApiResponse::errorResponse(__($status), 422, ['email' => [__($status)]]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::successResponse(null, __($status));
        }

        return ApiResponse::errorResponse(__($status), 422, ['email' => [__($status)]]);
    }
}
