<?php

namespace App\Console\Commands;

use App\Contracts\PluginRepositoryInterface;
use App\Services\TrendingTracker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;

#[Signature('plugins:refresh-trending {--no-recover : Do not trigger recovery even if marker is missing}')]
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
        $autoRecover = (bool) config('plugins.trending.auto_recover', true);
        $recoveredKey = config('plugins.trending.keys.recovered', 'plugins:trending_recovered');

        if ($autoRecover && ! $this->option('no-recover') && ! Redis::exists($recoveredKey)) {
            $this->info('Trending data not recovered in Redis yet. Running recovery...');
            Artisan::call('plugins:recover-trending', ['--no-refresh' => true]);
        }

        $daysLimit = (int) config('plugins.trending.days_limit');
        $weightView = (float) config('plugins.trending.weights.view');
        $weightComment = (float) config('plugins.trending.weights.comment');
        $weightStar = (float) config('plugins.trending.weights.star');
        $limit = (int) config('plugins.trending.limit', 100);

        $activeIds = TrendingTracker::getActivePluginIds($daysLimit);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        if (empty($activeIds)) {
            $this->fallbackToAllTime($limit, $zsetKey, $hashKey);
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
            $this->fallbackToAllTime($limit, $zsetKey, $hashKey);
            return;
        }

        // Sort descending
        arsort($scoredPlugins);

        // Take top $limit
        $topIds = array_slice(array_keys($scoredPlugins), 0, $limit);

        // Fetch full objects from DB
        $plugins = $this->pluginRepository->findApprovedByIds($topIds);

        if ($plugins->isEmpty()) {
            $this->fallbackToAllTime($limit, $zsetKey, $hashKey);
            return;
        }

        $this->storeResultsToRedis($plugins, $limit, $zsetKey, $hashKey, false, $scoredPlugins);

        $this->info("Successfully refreshed {$plugins->count()} trending plugins to Redis ZSET.");
    }

    private function fallbackToAllTime(int $limit, string $zsetKey, string $hashKey): void
    {
        $results = $this->pluginRepository->getTopAllTimePlugins($limit);

        if ($results->isEmpty()) {
            Redis::del($zsetKey);
            Redis::del($hashKey);
            $this->info("No plugins found. Cleared trending cache.");
            return;
        }

        $this->storeResultsToRedis($results, $limit, $zsetKey, $hashKey, true);
        $this->info("Successfully refreshed {$results->count()} fallback plugins to Redis ZSET.");
    }

    /**
     * @param  Collection<int, \App\Models\Plugin>  $plugins
     * @param  array<string, float|int>  $scoredPlugins
     */
    private function storeResultsToRedis(
        Collection $plugins,
        int $limit,
        string $zsetKey,
        string $hashKey,
        bool $isFallback,
        array $scoredPlugins = []
    ): void {
        if ($plugins->isEmpty()) {
            Redis::del($zsetKey);
            Redis::del($hashKey);
            return;
        }

        $tmpZsetKey = $zsetKey . '_tmp';
        $tmpHashKey = $hashKey . '_tmp';

        Redis::del($tmpZsetKey);
        Redis::del($tmpHashKey);

        $baseScore = $limit;

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
