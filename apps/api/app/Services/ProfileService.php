<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    /**
     * Update user's basic profile information.
     */
    public function updateProfile(User $user, array $data): User
    {
        // Nếu user gửi yêu cầu xóa avatar (avatarLink = null)
        if (array_key_exists('avatarLink', $data) && $data['avatarLink'] === null) {
            $this->deletePhysicalAvatar($user->avatarLink);
        }

        $user->update($data);
        return $user;
    }

    /**
     * Upload and update user's avatar.
     */
    public function updateAvatar(User $user, UploadedFile $file): User
    {
        // Xóa avatar cũ trước khi lưu cái mới
        $this->deletePhysicalAvatar($user->avatarLink);

        $path = $file->store('avatars', 'public');
        $user->avatarLink = config('app.url') . '/storage/' . $path;
        $user->save();

        return $user;
    }

    /**
     * Delete the physical avatar file from local storage if it exists.
     */
    private function deletePhysicalAvatar(?string $avatarLink): void
    {
        if ($avatarLink && str_starts_with($avatarLink, config('app.url') . '/storage/')) {
            $oldPath = str_replace(config('app.url') . '/storage/', '', $avatarLink);
            Storage::disk('public')->delete($oldPath);
        }
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
