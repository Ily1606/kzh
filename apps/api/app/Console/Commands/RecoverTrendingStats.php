<?php

namespace App\Console\Commands;

use App\Contracts\CommentRepositoryInterface;
use App\Contracts\PluginDailyViewRepositoryInterface;
use App\Contracts\StarRepositoryInterface;
use App\Services\TrendingTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;

#[Signature('plugins:recover-trending {--no-refresh : Do not trigger plugins:refresh-trending after recovery}')]
#[Description('Recover missing trending statistics from database (comments and stars) into Redis')]
class RecoverTrendingStats extends Command
{
    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly StarRepositoryInterface $starRepository,
        private readonly PluginDailyViewRepositoryInterface $pluginDailyViewRepository,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $daysLimit = (int) config('plugins.trending.days_limit');
        $this->info("Recovering trending data for the past {$daysLimit} days...");

        // 1. Recover Comments
        $this->info('Recovering comments...');
        $comments = $this->commentRepository->getDailyCommentCounts($daysLimit);
        $this->saveBatchToRedis('comments', $comments);
        $this->info("Recovered {$comments->count()} comment records.");

        // 2. Recover Stars
        $this->info('Recovering stars...');
        $stars = $this->starRepository->getDailyStarCounts($daysLimit);
        $this->saveBatchToRedis('stars', $stars);
        $this->info("Recovered {$stars->count()} star records.");

        // 3. Recover Views
        $this->info('Recovering daily views...');
        $views = $this->pluginDailyViewRepository->getDailyViewCounts($daysLimit);
        $this->saveBatchToRedis('views', $views);
        $this->info("Recovered {$views->count()} daily view records.");

        // Mark trending as recovered in Redis
        $recoveredKey = config('plugins.trending.keys.recovered', 'plugins:trending_recovered');
        Redis::set($recoveredKey, '1');

        // 4. Re-run the refresh command
        if (! $this->option('no-refresh')) {
            $this->info('Rebuilding trending ZSET...');
            Artisan::call('plugins:refresh-trending');
            $this->info(Artisan::output());
        }

        $this->info('Trending data recovery complete!');
    }

    /**
     * Bulk save recovered interaction statistics into Redis using pipelines to minimize round-trips.
     * Uses SET with NX (not exists) so that concurrent live increments (TrendingTracker::track*)
     * are never overwritten, and repeated recovery runs remain idempotent.
     *
     * @param  string  $type
     * @param  Collection<int, mixed>  $records
     */
    private function saveBatchToRedis(string $type, Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $ttl = TrendingTracker::ttl();

        // Write using pipelined SET NX to preserve concurrent live traffic
        $records->chunk(500)->each(function ($chunk) use ($type, $ttl) {
            Redis::pipeline(function ($pipe) use ($chunk, $type, $ttl) {
                foreach ($chunk as $record) {
                    $statKey = "trending:stats:{$type}:{$record->date}:{$record->plugin_id}";
                    $activeKey = "trending:active_plugins:{$record->date}";

                    $pipe->set($statKey, (int) $record->count, ['nx', 'ex' => $ttl]);
                    $pipe->sadd($activeKey, $record->plugin_id);
                    $pipe->expire($activeKey, $ttl);
                }
            });
        });
    }
}
