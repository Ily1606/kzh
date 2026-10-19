<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Redis;

final class TrendingTracker
{
    public static function ttl(): int
    {
        // +1 day so the oldest bucket in the window is still there at the end of the day
        return 86400 * ((int) config('plugins.trending.days_limit') + 1);
    }

    public static function trackView(string $pluginId): void
    {
        self::trackInteraction('views', $pluginId, 1);
    }

    public static function trackComment(string $pluginId, int $increment = 1, ?CarbonInterface $at = null): void
    {
        self::trackInteraction('comments', $pluginId, $increment, $at);
    }

    public static function trackStar(string $pluginId, int $increment = 1, ?CarbonInterface $at = null): void
    {
        self::trackInteraction('stars', $pluginId, $increment, $at);
    }

    private static function trackInteraction(string $type, string $pluginId, int $increment, ?CarbonInterface $at = null): void
    {
        $date = ($at ?? now())->format('Y-m-d');
        $statKey = "trending:stats:{$type}:{$date}:{$pluginId}";

        // The bucket is already outside the window (expired): nothing to subtract.
        if ($increment < 0 && ! Redis::exists($statKey)) {
            return;
        }

        $activeKey = "trending:active_plugins:{$date}";

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
     * Get the active plugin IDs for the last N days.
     *
     * @return array<int, string>
     */
    public static function getActivePluginIds(int $daysLimit): array
    {
        $keys = [];
        for ($i = 0; $i < $daysLimit; $i++) {
            $keys[] = 'trending:active_plugins:' . now()->subDays($i)->format('Y-m-d');
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
     * @param array<int, string> $pluginIds
     * @return array<string, array{views: int, comments: int, stars: int}>
     */
    public static function getBulkInteractionCounts(array $pluginIds, int $daysLimit): array
    {
        if (empty($pluginIds)) {
            return [];
        }

        $types = ['views', 'comments', 'stars'];
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
                        $keys[] = "trending:stats:{$type}:{$date}:{$id}";
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
}
