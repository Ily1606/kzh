<?php

namespace App\Observers;

use App\Jobs\CascadeDeleteUserData;
use App\Mail\AccountLockedMail;
use App\Models\User;
use App\Models\UsernameHistory;
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

        // If username or name changed, record history (for username) and refresh trending cache for user's plugins
        if ($user->wasChanged(['username', 'name'])) {
            if ($user->wasChanged('username')) {
                $this->recordUsernameHistory($user);
            }

            $ids = $user->plugins()->pluck('id')->all();
            if ($ids) {
                TrendingTracker::refreshPlugins($ids);
            }
        }
    }

    /**
     * Lưu username cũ vào bảng history với thời gian khóa.
     */
    private function recordUsernameHistory(User $user): void
    {
        $oldUsername = $user->getOriginal('username');
        $newUsername = $user->username;

        if ($oldUsername !== $newUsername) {
            $reservationDays = config('users.username_reservation_days', 30);
            $availableAt = now()->addDays($reservationDays);

            UsernameHistory::create([
                'user_id' => $user->getKey(),
                'username' => strtolower($oldUsername),
                'released_at' => now(),
                'available_at' => $availableAt,
            ]);
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
     * Handle the User "forceDeleting" event.
     */
    public function forceDeleting(User $user): void
    {
        // Delete the profile so UserProfileObserver can handle avatar file cleanup
        $user->profile?->delete();
    }
}
