<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Services\AuthService;
use App\Support\ApiResponse;
use App\Http\Resources\UserResource;
use App\Services\EmailService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $auth = $this->authService->register($request->validated());

        return ApiResponse::successResponse([
            'user' => $auth['user'],
            'token' => $auth['token'],
        ], __('api.registration_successful'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $auth = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return ApiResponse::successResponse([
            'user' => $auth['user'],
            'token' => $auth['token'],
        ], __('api.login_successful'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::successResponse(null, __('api.logout_successful'));
    }

    public function user(Request $request): JsonResponse
    {
        return ApiResponse::successResponse($request->user(), __('api.user_retrieved'));
    }
}
