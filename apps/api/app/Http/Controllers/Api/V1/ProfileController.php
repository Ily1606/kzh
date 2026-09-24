<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\ApiMessage;
use App\Http\Resources\UserResource;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
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

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile($request->user(), $request->validated());

        return ApiResponse::successResponse(new UserResource($user), 'User profile updated successfully.');
    }

    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $this->profileService->updateAvatar($request->user(), $request->file('avatar'));

        return ApiResponse::successResponse(new UserResource($user), 'Avatar updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $user = $this->profileService->updatePassword(
            $request->user(),
            $data['current_password'],
            $data['new_password']
        );

        return ApiResponse::successResponse(new UserResource($user), 'Password updated successfully.');
    }
}
