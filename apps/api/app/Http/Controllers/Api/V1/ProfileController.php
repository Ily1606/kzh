<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\RequestEmailChangeRequest;
use App\Http\Requests\VerifyEmailChangeRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    /**
     * Get user's profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->profileService->getProfile($request->user());

        return ApiResponse::successResponse(new UserResource($user), __('api.user_retrieved'));
    }

    /**
     * Update user's profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile($request->user(), $request->validated());

        return ApiResponse::successResponse(new UserResource($user), __('api.profile_updated'));
    }

    /**
     * Update user's avatar
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $this->profileService->updateAvatar($request->user(), $request->file('avatar'));

        return ApiResponse::successResponse(new UserResource($user), __('api.avatar_updated'));
    }

    /**
     * Update user's password
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $this->profileService->updatePassword(
            $request->user(),
            $data['current_password'],
            $data['new_password']
        );

        return ApiResponse::successResponse(new UserResource($user), __('api.password_updated'));
    }

    public function requestEmailChange(RequestEmailChangeRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $this->profileService->requestEmailChange($user, $data['current_password'], $data['new_email']);

        return ApiResponse::successResponse(null, __('api.email_change_requested'));
    }

    public function verifyEmailChange(VerifyEmailChangeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $this->profileService->verifyEmailChange($request->user(), $data['token']);

        return ApiResponse::successResponse(new UserResource($user), __('api.email_updated'));
    }
}
