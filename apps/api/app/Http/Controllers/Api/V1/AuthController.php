<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\ApiMessage;
use App\Models\User;
use App\Support\ApiResponse;
use App\Http\Resources\UserResource;
use App\Services\EmailService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request, EmailService $emailService): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($data);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        try {
            $emailService->sendWelcomeEmail($user);
        } catch (Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage());
        }

        return ApiResponse::successResponse(new UserResource($user), ApiMessage::REGISTRATION_SUCCESSFUL->value, 201);
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

        return ApiResponse::successResponse(new UserResource($request->user()), ApiMessage::LOGIN_SUCCESSFUL->value);
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
        return ApiResponse::successResponse(new UserResource($request->user()), ApiMessage::USER_RETRIEVED->value);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatarLink' => ['sometimes', 'nullable', 'url', 'max:255'],
            'githubName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'githubLink' => ['sometimes', 'nullable', 'url', 'max:255'],
        ]);

        $user->update($data);

        return ApiResponse::successResponse(new UserResource($user), 'User profile updated successfully.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return ApiResponse::errorResponse('Current password is incorrect.', 422, [
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = $data['new_password'];
        $user->save();

        return ApiResponse::successResponse(new UserResource($user), 'Password updated successfully.');
    }
}
