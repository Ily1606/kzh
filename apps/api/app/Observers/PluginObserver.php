<?php

namespace App\Observers;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
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
            $this->cleanupTrendingCache($plugin->id);
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
        $this->cleanupTrendingCache($plugin->id);
    }

    /**
     * Handle the Plugin "forceDeleted" event (permanent delete).
     */
    public function forceDeleted(Plugin $plugin): void
    {
        $this->cleanupTrendingCache($plugin->id);
    }

    /**
     * Remove the plugin from all trending caches in Redis.
     */
    public function cleanupTrendingCache(string $pluginId): void
    {
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');

        // 1. Remove from ZSET, objects HASH, and views buffer
        if ($zsetKey) {
            Redis::zrem($zsetKey, $pluginId);
        }
        if ($hashKey) {
            Redis::hdel($hashKey, $pluginId);
        }
        if ($bufferKey) {
            Redis::hdel($bufferKey, $pluginId);
        }

        // 2. Remove from active sets across the sliding window
        $daysLimit = (int) config('plugins.trending.days_limit', 30);
        for ($i = 0; $i < $daysLimit; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            Redis::srem("trending:active_plugins:{$date}", $pluginId);
        }
    }
}
