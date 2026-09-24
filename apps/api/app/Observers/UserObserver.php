<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // If avatarLink was changed, delete the old physical file to save space
        if ($user->isDirty('avatarLink')) {
            $oldAvatar = $user->getOriginal('avatarLink');
            
            // We only delete if it's a relative path stored locally, 
            // though right now we only store relative paths. We can just pass it to Storage.
            if ($oldAvatar && !str_starts_with($oldAvatar, 'http')) {
                Storage::disk('public')->delete($oldAvatar);
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // When user is deleted, also remove their avatar
        if ($user->avatarLink && !str_starts_with($user->avatarLink, 'http')) {
            Storage::disk('public')->delete($user->avatarLink);
        }
    }
}
