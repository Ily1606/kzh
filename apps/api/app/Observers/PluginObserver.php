<?php

namespace App\Observers;

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
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        if ($plugin->status !== PluginStatus::Approved) {
            Redis::zrem($zsetKey, $plugin->id);
            Redis::hdel($hashKey, $plugin->id);
        } else {
            // If it is still approved, we only update the hash if it exists in the ZSET
            if (Redis::zscore($zsetKey, $plugin->id) !== null) {
                Redis::hset($hashKey, $plugin->id, serialize(clone $plugin));
            }
        }
    }

    /**
     * Handle the Plugin "deleted" event.
     */
    public function deleted(Plugin $plugin): void
    {
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        Redis::zrem($zsetKey, $plugin->id);
        Redis::hdel($hashKey, $plugin->id);
    }
}
