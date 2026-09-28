<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserObserver
{
    /**
     * Handle the User "forceDeleting" event.
     */
    public function forceDeleting(User $user): void
    {
        // When user is being deleted, also remove their avatar from storage
        $avatarLink = $user->profile?->avatar_link;

        if ($avatarLink && !str_starts_with($avatarLink, 'http')) {
            Storage::disk('public')->delete($avatarLink);
        }
    }
}
