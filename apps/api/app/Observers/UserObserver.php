<?php

namespace App\Observers;

use App\Enums\PluginStatus;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

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

        if ($user->wasChanged('locked_at') && $user->locked_at === null) {
            $todayKey = 'trending:active_plugins:' . now()->format('Y-m-d');
            $user->plugins()
                ->where('status', PluginStatus::Approved)
                ->whereNotNull('approved_at')
                ->each(function ($plugin) use ($todayKey) {
                    Redis::sadd($todayKey, $plugin->id);
                });
        }
    }
    /**
     * Handle the User "deleted" event (soft delete).
     */
    public function deleted(User $user): void
    {
        $user->tokens()->delete();

        $user->plugins()->each(fn ($plugin) => $plugin->delete());

        $user->comments()->each(fn ($comment) => $comment->delete());

        $user->stars()->get()->each(fn ($star) => $star->delete());
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $user->plugins()->onlyTrashed()->each(fn ($plugin) => $plugin->restore());
        $user->comments()->onlyTrashed()->each(fn ($comment) => $comment->restore());
        $user->stars()->onlyTrashed()->get()->each(fn ($star) => $star->restore());
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
