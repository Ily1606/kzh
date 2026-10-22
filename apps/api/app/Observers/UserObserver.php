<?php

namespace App\Observers;

use App\Jobs\CascadeDeleteUserData;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('locked_at') && $user->locked_at !== null) {
            $user->tokens()->delete();

            $user->plugins()->each(function ($plugin) {
                app(PluginObserver::class)->cleanupTrendingCache($plugin->id);
            });
        }
    }
    /**
     * Handle the User "deleted" event (soft delete).
     */
    public function deleted(User $user): void
    {
        CascadeDeleteUserData::dispatch($user);
    }

    /**
     * Runs before the user's deleted_at is cleared, so only rows removed by the
     * cascade (deleted at or after the user) are restored.
     */
    public function restoring(User $user): void
    {
        $since = $user->deleted_at;

        $user->plugins()->onlyTrashed()->where('deleted_at', '>=', $since)->lazyById()->each(fn ($p) => $p->restore());
        $user->comments()->onlyTrashed()->where('deleted_at', '>=', $since)->lazyById()->each(fn ($c) => $c->restore());
        $user->stars()->onlyTrashed()->where('deleted_at', '>=', $since)->get()->each(fn ($s) => $s->restore());
    }

    /**
     * Handle the User "forceDeleting" event.
     */
    public function forceDeleting(User $user): void
    {
        // Delete the profile so UserProfileObserver can handle avatar file cleanup
        $user->profile?->delete();
    }
}
