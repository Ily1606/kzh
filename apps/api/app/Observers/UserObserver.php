<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserObserver
{
    /**
     * Handle the User "forceDeleted" event.
     */
    public function forceDeleted(User $user): void
    {
        // When user is deleted, also remove their avatar from storage
        $avatarLink = $user->profile?->avatar_link;
        if ($avatarLink && !str_starts_with($avatarLink, 'http')) {
            Storage::disk('public')->delete($avatarLink);
        }
    }
}
