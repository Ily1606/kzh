<?php

namespace App\Console\Commands;

use App\Contracts\PluginRepositoryInterface;
use App\Services\TrendingTracker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

#[Signature('plugins:refresh-trending')]
#[Description('Calculate and cache the top trending plugins using Hacker News algorithm')]
class RefreshTrendingPlugins extends Command
{
    public function __construct(
        private readonly PluginRepositoryInterface $pluginRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $daysLimit = (int) config('plugins.trending.days_limit', 7);
        $weightView = (float) config('plugins.trending.weights.view');
        $weightComment = (float) config('plugins.trending.weights.comment');
        $weightStar = (float) config('plugins.trending.weights.star');
        $masterLimit = (int) config('plugins.trending.master_limit');

        $activeIds = TrendingTracker::getActivePluginIds($daysLimit);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        if (empty($activeIds)) {
            // Fallback to all-time top
            $results = $this->pluginRepository->getTopAllTimePlugins($masterLimit);

            if ($results->isEmpty()) {
                Redis::del($zsetKey);
                Redis::del($hashKey);
                $this->info("No plugins found. Cleared trending cache.");
                return;
            }

            $this->storeResultsToRedis($results, $masterLimit, $zsetKey, $hashKey, true);
            $this->info("Successfully refreshed {$results->count()} fallback plugins to Redis ZSET.");
            return;
        }

        // We have active plugins, get their recent counts
        $interactions = TrendingTracker::getBulkInteractionCounts($activeIds, $daysLimit);

        $scoredPlugins = [];
        foreach ($interactions as $id => $counts) {
            $score = ($counts['views'] * $weightView) +
                     ($counts['comments'] * $weightComment) +
                     ($counts['stars'] * $weightStar);

            if ($score > 0) {
                $scoredPlugins[$id] = $score;
            }
        }

        if (empty($scoredPlugins)) {
            // Unlikely, but fallback just in case
            $results = $this->pluginRepository->getTopAllTimePlugins($masterLimit);
            $this->storeResultsToRedis($results, $masterLimit, $zsetKey, $hashKey, true);
            $this->info("Refreshed {$results->count()} fallback plugins to Redis ZSET.");
            return;
        }

        // Sort descending
        arsort($scoredPlugins);

        // Take top $masterLimit
        $topIds = array_slice(array_keys($scoredPlugins), 0, $masterLimit);

        // Fetch full objects from DB
        $plugins = $this->pluginRepository->findApprovedByIds($topIds);

        // Put scores back into plugins (transient) if needed, but we don't strictly need it in the API response.
        // Or we can just sort the collection properly. Since findApprovedByIds already sorts by the given ID array.

        $this->storeResultsToRedis($plugins, $masterLimit, $zsetKey, $hashKey, false, $scoredPlugins);

        $this->info("Successfully refreshed {$plugins->count()} trending plugins to Redis ZSET.");
    }

    private function storeResultsToRedis($plugins, $masterLimit, $zsetKey, $hashKey, $isFallback, $scoredPlugins = [])
    {
        $tmpZsetKey = $zsetKey . '_tmp';
        $tmpHashKey = $hashKey . '_tmp';

        Redis::del($tmpZsetKey);
        Redis::del($tmpHashKey);

        $baseScore = $masterLimit;

        foreach ($plugins as $index => $plugin) {
            if ($isFallback) {
                $score = $baseScore - $index;
            } else {
                $score = $scoredPlugins[$plugin->id] ?? 0;
            }

            Redis::zadd($tmpZsetKey, $score, $plugin->id);
            Redis::hset($tmpHashKey, $plugin->id, serialize($plugin));
        }

        Redis::rename($tmpZsetKey, $zsetKey);
        Redis::rename($tmpHashKey, $hashKey);
    }
}
