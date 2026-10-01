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
     * The request is rate limited per IP, together with the rest of the auth
     * endpoint group.
     *
     * Responses:
     * - 201: the account was created and a token was issued.
     * - 422: validation failed.
     *
     * The 201 and 422 responses are inferred by Scramble from the return value
     * and the validation rules on RegisterRequest, so they are not declared
     * explicitly. The 429 has to be declared because it comes from the
     * `throttle:auth` middleware, which Scramble does not track.
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
     * Exchange credentials for an API token.
     *
     * The request is rate limited per IP, together with the rest of the auth
     * endpoint group. This is the endpoint an attacker hammers, so the 429 is
     * part of the contract rather than an accident.
     *
     * Responses:
     * - 200: the credentials were valid and a token was issued.
     * - 422: validation failed, or the credentials were rejected.
     *
     * The 200 and 422 responses are inferred by Scramble from the return value
     * and the validation rules on LoginRequest, so they are not declared
     * explicitly. The 429 has to be declared because it comes from the
     * `throttle:auth` middleware, which Scramble does not track.
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

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout(
            $request->user(),
            RequestContext::fromRequest($request),
        );

        return ApiResponse::successResponse(null, __('api.logout_successful'));
    }
}
