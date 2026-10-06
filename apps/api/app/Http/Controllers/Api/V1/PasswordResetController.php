<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendResetLinkRequest;
use App\Services\PasswordResetService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService) {}

    /**
     * Send a password reset link.
     *
     * The request is rate limited per IP, together with the rest of the auth
     *
     * @unauthenticated
     */
    #[Response(status: 429, description: 'Too many attempts. Retry after the rate limit window resets.')]
    public function sendResetLinkEmail(SendResetLinkRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->only('email'));

        return ApiResponse::successResponse(null, __('api.password_reset_link_sent_if_exists'));
    }

    /**
     * Complete a password reset.
     *
     * Rate limited per IP together with the rest of the auth endpoint group.
     *
     * @unauthenticate
     */
    #[Response(status: 429, description: 'Too many attempts. Retry after the rate limit window resets.')]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        return ApiResponse::successResponse(null, __(Password::PASSWORD_RESET));
    }
}
