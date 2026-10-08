<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use App\Support\RequestContext;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Register a new account.
     *
     * @unauthenticated
     */
    #[Response(status: 429, description: 'Too many attempts. Retry after the rate limit window resets.')]
    public function register(RegisterRequest $request): JsonResponse
    {
        $auth = $this->authService->register(
            $request->validated(),
            RequestContext::fromRequest($request),
        );

        return ApiResponse::successResponse([
            'user' => new UserResource($auth['user']->loadMissing('profile')),
            'token' => $auth['token'],
        ], __('api.registration_successful'), 201);
    }

    /**
     * Login
     *
     * @unauthenticated
     */
    #[Response(status: 429, description: 'Too many attempts. Retry after the rate limit window resets.')]
    public function login(LoginRequest $request): JsonResponse
    {
        $auth = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            RequestContext::fromRequest($request),
        );

        return ApiResponse::successResponse([
            'user' => new UserResource($auth['user']->loadMissing('profile')),
            'token' => $auth['token'],
        ], __('api.login_successful'));
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout(
            $request->user(),
            RequestContext::fromRequest($request),
        );

        return ApiResponse::successResponse(null, __('api.logout_successful'));
    }
}
