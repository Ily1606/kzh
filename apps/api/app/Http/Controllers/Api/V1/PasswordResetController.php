<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendResetLinkRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Support\ApiResponse;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService)
    {
    }

    public function sendResetLinkEmail(SendResetLinkRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->only('email'));

        return ApiResponse::successResponse(null, __('api.password_reset_link_sent_if_exists'));
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        return ApiResponse::successResponse(null, __(Password::PASSWORD_RESET));
    }
}
