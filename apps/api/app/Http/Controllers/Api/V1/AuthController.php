<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\ApiMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($data);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return ApiResponse::successResponse($user, ApiMessage::REGISTRATION_SUCCESSFUL->value, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['isActive'] = true;
        $credentials['isDeleted'] = false;

        if (! Auth::attempt($credentials, remember: true)) {
            return ApiResponse::errorResponse(ApiMessage::INVALID_CREDENTIALS->value, 422, [
                'email' => [ApiMessage::INVALID_CREDENTIALS->value],
            ]);
        }

        $request->session()->regenerate();

        return ApiResponse::successResponse($request->user(), ApiMessage::LOGIN_SUCCESSFUL->value);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::successResponse(null, ApiMessage::LOGOUT_SUCCESSFUL->value);
    }

    public function user(Request $request): JsonResponse
    {
        return ApiResponse::successResponse($request->user(), ApiMessage::USER_RETRIEVED->value);
    }
}
