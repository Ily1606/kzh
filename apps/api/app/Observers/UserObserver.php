<?php

namespace App\Observers;

use App\Jobs\CascadeDeleteUserData;
use App\Mail\AccountLockedMail;
use App\Models\User;
use App\Services\TrendingTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('locked_at') && $user->locked_at !== null) {
            $user->tokens()->delete();

            $pluginIds = $user->plugins()->pluck('id')->all();
            if (! empty($pluginIds)) {
                TrendingTracker::cleanupPlugins($pluginIds, false);
            }

            $this->sendAccountLockedEmail($user);
        }
    }

    /**
     * Send an email notifying the user that their account has been locked.
     */
    protected function sendAccountLockedEmail(User $user): void
    {
        if (empty($user->email)) {
            return;
        }

        try {
            DB::afterCommit(function () use ($user): void {
                Mail::to($user->email)->queue(
                    (new AccountLockedMail($user))->afterCommit()
                );
            });
        } catch (Throwable $e) {
            report($e);
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
        $user->commentReports()->onlyTrashed()->where('deleted_at', '>=', $since)->get()->each(fn ($r) => $r->restore());
        $user->pluginReports()->onlyTrashed()->where('deleted_at', '>=', $since)->get()->each(fn ($r) => $r->restore());
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
