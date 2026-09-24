<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    /**
     * Update user's basic profile information.
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    /**
     * Upload and update user's avatar.
     */
    public function updateAvatar(User $user, ?UploadedFile $file): User
    {
        $user->avatarLink = $file?->store('avatars', 'public');

        $user->save();

        return $user;
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
