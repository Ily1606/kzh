<?php

namespace App\Observers;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Jobs\CascadeDeletePluginData;
use App\Models\Plugin;
use App\Services\TrendingTracker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PluginObserver
{
    /**
     * Handle the Plugin "updated" event.
     */
    public function updated(Plugin $plugin): void
    {
        $hashKey = config('plugins.trending.keys.objects');

        // 1. If the plugin is no longer approved, remove it immediately from trending
        if ($plugin->status !== PluginStatus::Approved || $plugin->approved_at === null) {
            TrendingTracker::cleanupPlugin($plugin->id);

            return;
        }

        // 2. If the plugin is currently cached in trending objects, update the cached object
        if ($hashKey && Redis::hexists($hashKey, $plugin->id)) {
            $fresh = app(PluginRepositoryInterface::class)
                ->findApprovedByIds([$plugin->id])
                ->first();

            if ($fresh) {
                Redis::hset($hashKey, $plugin->id, serialize($fresh));
            }
        }
    }

    /**
     * Handle the Plugin "deleted" event (soft delete).
     */
    public function deleted(Plugin $plugin): void
    {
        CascadeDeletePluginData::dispatch($plugin);
    }

    /**
     * Handle the Plugin "forceDeleted" event (permanent delete).
     */
    public function forceDeleted(Plugin $plugin): void
    {
        TrendingTracker::cleanupPlugin($plugin->id);
    }
}
