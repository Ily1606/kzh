<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

final class TrendingTracker
{
    private const TTL = 86400 * 8; // 8 days

    public static function trackView(string $pluginId): void
    {
        self::trackInteraction('views', $pluginId, 1);
    }

    public static function trackComment(string $pluginId, int $increment = 1): void
    {
        self::trackInteraction('comments', $pluginId, $increment);
    }

    public static function trackStar(string $pluginId, int $increment = 1): void
    {
        self::trackInteraction('stars', $pluginId, $increment);
    }

    private static function trackInteraction(string $type, string $pluginId, int $increment): void
    {
        $date = now()->format('Y-m-d');
        
        $statKey = "trending:stats:{$type}:{$date}:{$pluginId}";
        $activeKey = "trending:active_plugins:{$date}";

        if ($increment > 0) {
            Redis::incrby($statKey, $increment);
            Redis::sadd($activeKey, $pluginId);
        } else {
            Redis::incrby($statKey, $increment);
        }

        Redis::expire($statKey, self::TTL);
        Redis::expire($activeKey, self::TTL);
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

        $allKeys = [];
        // To map the flat MGET result back to plugins
        $keyMap = []; 

        foreach ($pluginIds as $id) {
            foreach ($types as $type) {
                foreach ($dates as $date) {
                    $key = "trending:stats:{$type}:{$date}:{$id}";
                    $allKeys[] = $key;
                    $keyMap[] = ['id' => $id, 'type' => $type];
                }
            }
        }

        $values = Redis::mget($allKeys);
        
        $results = [];
        foreach ($pluginIds as $id) {
            $results[$id] = ['views' => 0, 'comments' => 0, 'stars' => 0];
        }

        foreach ($values as $index => $val) {
            if ($val !== null) {
                $id = $keyMap[$index]['id'];
                $type = $keyMap[$index]['type'];
                $results[$id][$type] += (int) $val;
            }
        }

        return $results;
    }
}

