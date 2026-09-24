<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\ApiMessage;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::successResponse(new UserResource($request->user()), ApiMessage::USER_RETRIEVED->value);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatarLink' => ['sometimes', 'nullable', 'url', 'max:255'],
            'githubName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'githubLink' => ['sometimes', 'nullable', 'url', 'max:255'],
        ]);

        $user = $this->profileService->updateProfile($request->user(), $data);

        return ApiResponse::successResponse(new UserResource($user), 'User profile updated successfully.');
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $user = $this->profileService->updateAvatar($request->user(), $request->file('avatar'));

        return ApiResponse::successResponse(['avatarLink' => $user->avatarLink], 'Avatar updated successfully.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->profileService->updatePassword(
            $request->user(),
            $data['current_password'],
            $data['new_password']
        );

        return ApiResponse::successResponse(new UserResource($user), 'Password updated successfully.');
    }
}
