<?php

namespace App\Observers;

use App\Models\Plugin;
use App\Models\UserProfile;
use App\Services\TrendingTracker;
use Illuminate\Support\Facades\DB;
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
            if (UserProfile::isLocalPath($oldAvatar)) {
                DB::afterCommit(function () use ($oldAvatar): void {
                    Storage::disk(UserProfile::AVATAR_DISK)->delete($oldAvatar);
                });
            }

            // Refresh trending cache when avatar changes
            $userId = $profile->user_id;
            if ($userId) {
                $ids = Plugin::where('user_id', $userId)->pluck('id')->all();
                if ($ids) {
                    TrendingTracker::refreshPlugins($ids);
                }
            }
        }
    }

    /**
     * Handle the UserProfile "deleted" event.
     */
    public function deleted(UserProfile $profile): void
    {
        if ($profile->isLocalAvatar()) {
            $avatar = $profile->avatar_link;

            DB::afterCommit(function () use ($avatar): void {
                Storage::disk(UserProfile::AVATAR_DISK)->delete($avatar);
            });
        }
    }
}
