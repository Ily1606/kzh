<?php

namespace App\Console\Commands;

use App\Contracts\PluginRepositoryInterface;
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
        $daysLimit = config('plugins.trending.days_limit');
        $weightView = (float) config('plugins.trending.weights.view');
        $weightComment = (float) config('plugins.trending.weights.comment');
        $weightStar = (float) config('plugins.trending.weights.star');

        $gravity = (float) config('plugins.trending.gravity');
        $ageOffset = (float) config('plugins.trending.age_offset');

        $masterLimit = (int) config('plugins.trending.master_limit');

        $weights = [
            'view' => $weightView,
            'comment' => $weightComment,
            'star' => $weightStar,
        ];

        $results = $this->pluginRepository->getTrendingPlugins($daysLimit, $weights, $gravity, $ageOffset, $masterLimit);

        if ($results->isEmpty()) {
            $results = $this->pluginRepository->getTopAllTimePlugins($masterLimit);
        }

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        if ($results->isEmpty()) {
            Redis::del($zsetKey);
            Redis::del($hashKey);
            $this->info("No plugins found. Cleared trending cache.");
            return;
        }

        $tmpZsetKey = $zsetKey . '_tmp';
        $tmpHashKey = $hashKey . '_tmp';

        // Clear temporary keys just in case a previous run crashed
        Redis::del($tmpZsetKey);
        Redis::del($tmpHashKey);

        $baseScore = $masterLimit;
        foreach ($results as $index => $plugin) {
            // Since the DB has already perfectly sorted the results (including tie-breakers),
            // we can just use the index as the ZSET score (100, 99, 98...).
            // This avoids floating-point precision issues and simplifies the logic.
            $score = $baseScore - $index;

            Redis::zadd($tmpZsetKey, $score, $plugin->id);
            Redis::hset($tmpHashKey, $plugin->id, serialize($plugin));
        }

        // Atomically swap the temporary keys with the live keys to guarantee zero downtime
        Redis::rename($tmpZsetKey, $zsetKey);
        Redis::rename($tmpHashKey, $hashKey);

        $this->info("Successfully refreshed {$results->count()} trending plugins to Redis ZSET.");
    }
}
