<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

final class TrendingTracker
{
    public const INTERACTION_VIEWS = 'views';

    public const INTERACTION_COMMENTS = 'comments';

    public const INTERACTION_STARS = 'stars';

    public const INTERACTION_TYPES = [
        self::INTERACTION_VIEWS,
        self::INTERACTION_COMMENTS,
        self::INTERACTION_STARS,
    ];

    public static function activeKey(string $date): string
    {
        $prefix = (string) config('plugins.trending.keys.active_prefix', 'trending:active_plugins');

        return "{$prefix}:{$date}";
    }

    public static function statKey(string $type, string $date, string $pluginId): string
    {
        $prefix = (string) config('plugins.trending.keys.stats_prefix', 'trending:stats');

        return "{$prefix}:{$type}:{$date}:{$pluginId}";
    }

    public static function ttl(): int
    {
        // +1 day so the oldest bucket in the window is still there at the end of the day
        return 86400 * ((int) config('plugins.trending.days_limit') + 1);
    }

    public static function trackView(string $pluginId): void
    {
        self::trackInteraction(self::INTERACTION_VIEWS, $pluginId, 1);
    }

    public static function trackComment(string $pluginId, int $increment = 1, ?CarbonInterface $at = null): void
    {
        self::trackInteraction(self::INTERACTION_COMMENTS, $pluginId, $increment, $at);
    }

    public static function trackStar(string $pluginId, int $increment = 1, ?CarbonInterface $at = null): void
    {
        self::trackInteraction(self::INTERACTION_STARS, $pluginId, $increment, $at);
    }

    private static function trackInteraction(string $type, string $pluginId, int $increment, ?CarbonInterface $at = null): void
    {
        $date = ($at ?? now())->format('Y-m-d');
        $statKey = self::statKey($type, $date, $pluginId);

        // The bucket is already outside the window (expired): nothing to subtract.
        if ($increment < 0 && ! Redis::exists($statKey)) {
            return;
        }

        $activeKey = self::activeKey($date);

        if ($increment > 0) {
            Redis::incrby($statKey, $increment);
            Redis::sadd($activeKey, $pluginId);
        } elseif ($increment < 0) {
            $newVal = (int) Redis::incrby($statKey, $increment);
            if ($newVal < 0) {
                Redis::set($statKey, 0);
            }
        }

        Redis::expire($statKey, self::ttl());
        Redis::expire($activeKey, self::ttl());
    }

    /**
     * Decrement interaction counts for multiple items in bulk.
     *
     * @param  string  $type  'comments'|'stars'
     * @param  array<int, array{plugin_id: string, date: string, count: int}>  $items
     */
    public static function bulkDecrementInteractions(string $type, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $chunkSize = max(1, (int) config('plugins.cascade_delete.chunk_size', 500));

        foreach (array_chunk($items, $chunkSize) as $chunk) {
            $keys = [];
            foreach ($chunk as $item) {
                $keys[] = self::statKey($type, $item['date'], $item['plugin_id']);
            }

            $values = Redis::mget($keys);
            if (! is_array($values)) {
                continue;
            }

            $pipe = Redis::pipeline();
            foreach ($chunk as $index => $item) {
                if ($values[$index] !== null && $values[$index] !== false) {
                    $key = $keys[$index];
                    $current = (int) $values[$index];
                    $decrement = min($current, (int) $item['count']);
                    if ($decrement > 0) {
                        $pipe->decrby($key, $decrement);
                    }
                }
            }
            $pipe->exec();
        }
    }

    /**
     * Get the active plugin IDs for the last N days.
     *
     * @return array<int, string>
     */
    public static function getActivePluginIds(int $daysLimit): array
    {
        $keys = [];
        for ($i = 0; $i < $daysLimit; $i++) {
            $keys[] = self::activeKey(now()->subDays($i)->format('Y-m-d'));
        }

        if (empty($keys)) {
            return [];
        }

        $ids = Redis::sunion($keys);

        return is_array($ids) ? $ids : [];
    }

    /**
     * Get the total interaction counts for multiple plugins over the last N days in bulk.
     *
     * @param  array<int, string>  $pluginIds
     * @return array<string, array{views: int, comments: int, stars: int}>
     */
    public static function getBulkInteractionCounts(array $pluginIds, int $daysLimit): array
    {
        if (empty($pluginIds)) {
            return [];
        }

        $types = self::INTERACTION_TYPES;
        $dates = [];
        for ($i = 0; $i < $daysLimit; $i++) {
            $dates[] = now()->subDays($i)->format('Y-m-d');
        }

        $results = [];
        foreach ($pluginIds as $id) {
            $results[$id] = ['views' => 0, 'comments' => 0, 'stars' => 0];
        }

        foreach (array_chunk($pluginIds, 100) as $chunk) {
            $keys = [];
            // To map the flat MGET result back to plugins
            $keyMap = [];

            foreach ($chunk as $id) {
                foreach ($types as $type) {
                    foreach ($dates as $date) {
                        $keys[] = self::statKey($type, $date, $id);
                        $keyMap[] = ['id' => $id, 'type' => $type];
                    }
                }
            }

            if (empty($keys)) {
                continue;
            }

            $values = Redis::mget($keys);

            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $index => $val) {
                if ($val !== null) {
                    $id = $keyMap[$index]['id'];
                    $type = $keyMap[$index]['type'];
                    $results[$id][$type] += (int) $val;
                }
            }
        }

        return $results;
    }

    /**
     * Remove multiple plugins from all trending caches in Redis in bulk and flush associated cache keys.
     *
     * @param  array<int, string>  $pluginIds
     * @param  bool  $deleteStats  Whether to permanently delete historical trending:stats:* keys
     */
    public static function cleanupPlugins(array $pluginIds, bool $deleteStats = false): void
    {
        if (empty($pluginIds)) {
            return;
        }

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');
        $processingKey = $bufferKey ? $bufferKey.'_processing' : null;
        $daysLimit = (int) config('plugins.trending.days_limit', 30);
        $chunkSize = max(1, (int) config('plugins.cascade_delete.chunk_size', 500));

        // Execute all Redis operations in a single network roundtrip using pipeline
        $pipe = Redis::pipeline();

        // 1. Bulk remove from ZSET, objects HASH, views buffer, and processing buffer
        if ($zsetKey) {
            $pipe->zrem($zsetKey, ...$pluginIds);
        }
        if ($hashKey) {
            $pipe->hdel($hashKey, ...$pluginIds);
        }
        if ($bufferKey) {
            $pipe->hdel($bufferKey, ...$pluginIds);
        }
        if ($processingKey) {
            $pipe->hdel($processingKey, ...$pluginIds);
        }

        // 2. Bulk remove from active sets across the sliding window and optionally collect stat keys
        $statKeys = [];

        for ($i = 0; $i <= $daysLimit; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $pipe->srem(self::activeKey($date), ...$pluginIds);

            if ($deleteStats) {
                foreach ($pluginIds as $pluginId) {
                    foreach (self::INTERACTION_TYPES as $type) {
                        $statKeys[] = self::statKey($type, $date, $pluginId);
                    }
                }
            }
        }

        // 3. Bulk delete daily stat keys in chunks to avoid blocking Redis with gigantic payload
        if ($deleteStats && ! empty($statKeys)) {
            foreach (array_chunk($statKeys, $chunkSize) as $keysChunk) {
                $pipe->del($keysChunk);
            }
        }

        $pipe->exec();

        // 4. Clear star timeline caches
        foreach ($pluginIds as $pluginId) {
            Cache::forget(StarService::timelineCacheKey($pluginId));
        }
    }

    /**
     * Remove a plugin from all trending caches in Redis and flush associated cache keys.
     */
    public static function cleanupPlugin(string $pluginId, bool $deleteStats = false): void
    {
        self::cleanupPlugins([$pluginId], $deleteStats);
    }

    /**
     * Bulk refresh trending cache for multiple plugins.
     * Fetches all approved plugins in a single query and updates the cache hash.
     *
     * @param  array<int, string>  $pluginIds
     */
    public static function refreshPlugins(array $pluginIds): void
    {
        if (empty($pluginIds)) {
            return;
        }

        $hashKey = config('plugins.trending.keys.objects');

        if (! $hashKey) {
            return;
        }

        // Only refresh plugins that are actually in the cache
        $existingIds = Redis::hmget($hashKey, $pluginIds);
        $cachedIds = [];

        foreach ($existingIds as $index => $value) {
            if ($value !== null && $value !== false) {
                $cachedIds[] = $pluginIds[$index];
            }
        }

        if (empty($cachedIds)) {
            return;
        }

        // Single query to fetch all approved plugins
        $freshPlugins = app(PluginRepositoryInterface::class)
            ->findApprovedByIds($cachedIds)
            ->keyBy('id');

        if ($freshPlugins->isEmpty()) {
            return;
        }

        // Bulk update the cache hash
        $data = [];
        foreach ($freshPlugins as $plugin) {
            $data[$plugin->id] = serialize($plugin);
        }

        Redis::hmset($hashKey, $data);
    }
}
