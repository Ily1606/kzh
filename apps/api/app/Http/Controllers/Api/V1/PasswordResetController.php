<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService)
    {
    }

    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $this->passwordResetService->sendResetLink($request->only('email'));

        return ApiResponse::successResponse(null, __(Password::RESET_LINK_SENT));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->passwordResetService->resetPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        return ApiResponse::successResponse(null, __(Password::PASSWORD_RESET));
    }
}
