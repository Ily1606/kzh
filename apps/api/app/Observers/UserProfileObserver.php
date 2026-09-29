<?php

namespace App\Observers;

use App\Models\UserProfile;
use Illuminate\Support\Facades\Storage;

class UserProfileObserver
{
    /**
     * Handle the UserProfile "updated" event.
     */
    public function updated(UserProfile $profile): void
    {
        if ($profile->isDirty('avatar_link')) {
            $oldAvatar = $profile->getOriginal('avatar_link');

            if ($profile->isLocalAvatar($oldAvatar)) {
                Storage::disk(UserProfile::AVATAR_DISK)->delete($oldAvatar);
            }
        }
    }

    /**
     * Handle the UserProfile "deleted" event.
     */
    public function deleted(UserProfile $profile): void
    {
        if ($profile->isLocalAvatar()) {
            Storage::disk(UserProfile::AVATAR_DISK)->delete($profile->avatar_link);
        }
    }
}
