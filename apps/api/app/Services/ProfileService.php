<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class ProfileService
{

    public function __construct()
    {
    }

    public function getProfile(User $user): User
    {
        return $user->load('profile');
    }
    /**
     * Update user's basic profile information.
     */
    public function updateProfile(User $user, array $data): User
    {
        // Extract user data
        if (isset($data['name'])) {
            $user->name = $data['name'];
            $user->save();
        }

        // Extract profile data
        $profileData = [];
        if (array_key_exists('githubName', $data)) {
            $profileData['github_name'] = $data['githubName'];
        }
        if (array_key_exists('githubLink', $data)) {
            $profileData['github_link'] = $data['githubLink'];
        }

        if (!empty($profileData)) {
            if ($user->profile) {
                $user->profile->update($profileData);
            } else {
                $profileData['id'] = (string) Str::uuid();
                $user->profile()->create($profileData);
            }
        }

        return $user->refresh();
    }

    /**
     * Upload and update user's avatar.
     */
    public function updateAvatar(User $user, ?UploadedFile $file): User
    {
        $avatarPath = $file?->store('avatars', 'public');

        if ($user->profile) {
            $user->profile->avatar_link = $avatarPath;
            $user->profile->save();
        } else {
            $user->profile()->create([
                'id' => (string) Str::uuid(),
                'avatar_link' => $avatarPath,
            ]);
        }

        return $user->refresh();
    }

    /**
     * Update user's password.
     *
     * @throws ValidationException
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): User
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = $newPassword;
        $user->save();

        return $user;
    }
}
