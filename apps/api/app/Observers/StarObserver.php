<?php

namespace App\Observers;

use App\Contracts\PluginRepositoryInterface;
use App\Models\Star;
use App\Services\TrendingTracker;
use Illuminate\Support\Facades\Redis;

class StarObserver
{
    /**
     * Handle the Star "deleted" event (soft delete or force delete).
     */
    public function deleted(Star $star): void
    {
        $this->handleStarDeleted($star);
    }

    /**
     * Handle the Star "restored" event (un-delete).
     */
    public function restored(Star $star): void
    {
        $this->handleStarRestored($star);
    }

    /**
     * Decrement trending interaction and refresh cached star count in Redis.
     */
    private function handleStarDeleted(Star $star): void
    {
        // 1. Decrement daily star interaction count in Redis
        TrendingTracker::trackStar($star->plugin_id, -1, $star->created_at);

        // 2. If the plugin is currently cached in trending objects, update its cached star count
        $this->refreshCachedPluginStarCount($star->plugin_id);
    }

    /**
     * Re-increment trending interaction and refresh cached star count in Redis.
     */
    private function handleStarRestored(Star $star): void
    {
        // 1. Re-increment daily star interaction count in Redis
        TrendingTracker::trackStar($star->plugin_id, 1, $star->created_at);

        // 2. If the plugin is currently cached in trending objects, update its cached star count
        $this->refreshCachedPluginStarCount($star->plugin_id);
    }

    /**
     * If the plugin is cached in Redis trending objects, refresh its cached star count.
     */
    private function refreshCachedPluginStarCount(string $pluginId): void
    {
        $hashKey = config('plugins.trending.keys.objects');
        if ($hashKey && Redis::hexists($hashKey, $pluginId)) {
            $fresh = app(PluginRepositoryInterface::class)
                ->findApprovedByIds([$pluginId])
                ->first();

            if ($fresh) {
                Redis::hset($hashKey, $pluginId, serialize($fresh));
            }
        }
    }
}

