<?php

namespace App\Services;

use App\Contracts\UserRepositoryInterface;
use App\Events\Profile\UserAvatarUpdated;
use App\Events\Profile\UserPasswordUpdated;
use App\Events\Profile\UserProfileUpdated;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Retrieve the user's profile.
     */
    public function getProfile(User $user): User
    {
        return $user->loadMissing('profile');
    }

    /**
     * Update user's basic profile information.
     */
    public function updateProfile(User $user, array $data): User
    {
        $name = $data['name'] ?? null;

        $profileData = [];
        if (array_key_exists('githubName', $data)) {
            $profileData['github_name'] = $data['githubName'];
        }
        if (array_key_exists('githubLink', $data)) {
            $profileData['github_link'] = $data['githubLink'];
        }

        $user = $this->userRepository->updateProfile($user, $name, $profileData);
        UserProfileUpdated::dispatch($user);

        return $user;
    }

    /**
     * Upload and update user's avatar.
     */
    public function updateAvatar(User $user, ?UploadedFile $file): User
    {
        $avatarPath = $file?->store('avatars', UserProfile::AVATAR_DISK);

        try {
            $user = $this->userRepository->updateAvatar($user, $avatarPath);
        } catch (Throwable $e) {
            if ($avatarPath) {
                Storage::disk(UserProfile::AVATAR_DISK)->delete($avatarPath);
            }
            throw $e;
        }
        UserAvatarUpdated::dispatch($user);

        return $user;
    }

    /**
     * Update user's password.
     *
     * @throws ValidationException
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): User
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('api.current_password_incorrect')],
            ]);
        }

        $user = $this->userRepository->updatePassword($user, $newPassword);
        UserPasswordUpdated::dispatch($user);

        return $user;
    }
}
