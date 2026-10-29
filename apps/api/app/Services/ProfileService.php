<?php

namespace App\Services;

use App\Contracts\UserRepositoryInterface;
use App\Events\Profile\UserAvatarUpdated;
use App\Events\Profile\UserPasswordUpdated;
use App\Events\Profile\UserProfileUpdated;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\UsernameHelper;
use Laravel\Sanctum\PersonalAccessToken;
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
     * The public profile of the user carrying this username.
     *
     * @throws ModelNotFoundException when no user carries that username.
     */
    public function getProfileByUsername(string $username): User
    {
        $user = $this->userRepository->findByUsername(Str::lower($username));

        if ($user === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$username]);
        }

        return $user;
    }

    /**
     * Update user's basic profile information.
     */
    public function updateProfile(User $user, array $data): User
    {
        $userData = Arr::only($data, ['name', 'username']);

        $profileData = [];
        if (array_key_exists('githubLink', $data)) {
            $profileData['github_link'] = $data['githubLink'];
        }

        try {
            $user = $this->userRepository->updateProfile($user, $userData, $profileData);
        } catch (UniqueConstraintViolationException $e) {
            if (! UsernameHelper::isViolation($e)) {
                throw $e;
            }
            throw ValidationException::withMessages([
                'username' => __('api.username_taken'),
            ]);
        }

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

        DB::transaction(function () use ($user, $newPassword): void {
            $this->userRepository->updatePassword($user, $newPassword);

            $currentToken = $user->currentAccessToken();
            $this->userRepository->revokeTokensExcept(
                $user,
                $currentToken instanceof PersonalAccessToken ? $currentToken->getKey() : null,
            );
        });

        UserPasswordUpdated::dispatch($user);

        return $user;
    }
}
