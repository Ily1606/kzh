<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\ApiMessage;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
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
