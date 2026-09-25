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
            
            if ($oldAvatar && !str_starts_with($oldAvatar, 'http')) {
                Storage::disk('public')->delete($oldAvatar);
            }
        }
    }

    /**
     * Handle the UserProfile "deleted" event.
     */
    public function deleted(UserProfile $profile): void
    {
        if ($profile->avatar_link && !str_starts_with($profile->avatar_link, 'http')) {
            Storage::disk('public')->delete($profile->avatar_link);
        }
    }
}
