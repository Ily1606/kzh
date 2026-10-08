<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('plugins:recover-trending')]
#[Description('Recover missing trending statistics from database (comments and stars) into Redis')]
class RecoverTrendingStats extends Command
{
    public function handle()
    {
        $daysLimit = (int) config('plugins.trending.days_limit', 7);
        $this->info("Recovering trending data for the past {$daysLimit} days...");

        // 1. Recover Comments
        $this->info('Recovering comments...');
        $comments = DB::table('comments')
            ->select('plugin_id', DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays($daysLimit))
            ->groupBy('plugin_id', DB::raw('DATE(created_at)'))
            ->get();

        foreach ($comments as $comment) {
            $this->saveToRedis('comments', $comment->plugin_id, $comment->date, $comment->count);
        }
        $this->info("Recovered {$comments->count()} comment records.");

        // 2. Recover Stars
        $this->info('Recovering stars...');
        $stars = DB::table('stars')
            ->select('plugin_id', DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->whereNotNull('created_at')
            ->where('created_at', '>=', now()->subDays($daysLimit))
            ->groupBy('plugin_id', DB::raw('DATE(created_at)'))
            ->get();

        foreach ($stars as $star) {
            $this->saveToRedis('stars', $star->plugin_id, $star->date, $star->count);
        }
        $this->info("Recovered {$stars->count()} star records.");

        // 3. Recover Views
        $this->info('Recovering daily views...');
        $views = DB::table('plugin_daily_views')
            ->select('plugin_id', 'date', 'views_count as count')
            ->where('date', '>=', now()->subDays($daysLimit)->format('Y-m-d'))
            ->get();

        foreach ($views as $view) {
            $this->saveToRedis('views', $view->plugin_id, $view->date, $view->count);
        }
        $this->info("Recovered {$views->count()} daily view records.");

        // 4. Re-run the refresh command
        $this->info('Rebuilding trending ZSET...');
        Artisan::call('plugins:refresh-trending');
        $this->info(Artisan::output());
        $this->info('Trending data recovery complete!');
    }

    private function saveToRedis(string $type, string $pluginId, string $date, int $count)
    {
        $statKey = "trending:stats:{$type}:{$date}:{$pluginId}";
        $activeKey = "trending:active_plugins:{$date}";
        
        Redis::set($statKey, $count);
        Redis::expire($statKey, 86400 * 8);
        
        Redis::sadd($activeKey, $pluginId);
        Redis::expire($activeKey, 86400 * 8);
    }
}
