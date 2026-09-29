<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public $afterCommit = true;

    /**
     * Handle the User "forceDeleting" event.
     */
    public function forceDeleting(User $user): void
    {
        // Delete the profile so UserProfileObserver can handle avatar file cleanup
        $user->profile?->delete();
    }
}
