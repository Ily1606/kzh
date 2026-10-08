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

        // 1. Recover Comments (Accurate)
        $this->info('Recovering comments (accurate)...');
        $comments = DB::table('comments')
            ->select('plugin_id', DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays($daysLimit))
            ->groupBy('plugin_id', DB::raw('DATE(created_at)'))
            ->get();

        foreach ($comments as $comment) {
            $this->saveToRedis('comments', $comment->plugin_id, $comment->date, $comment->count);
        }
        $this->info("Recovered {$comments->count()} comment records.");

        // 2. Recover Views & Stars (Approximation)
        $this->info('Approximating views and stars (best effort)...');
        $this->warn('Note: Views and stars are approximated because historical timestamps are not stored.');
        
        $plugins = DB::table('plugins')
            ->select('id', 'view_count', 'star_count', 'created_at')
            ->where('status', 'approved')
            ->where(function($q) {
                $q->where('view_count', '>', 0)->orWhere('star_count', '>', 0);
            })
            ->get();

        $today = now()->format('Y-m-d');
        
        foreach ($plugins as $plugin) {
            $createdAt = \Carbon\Carbon::parse($plugin->created_at);
            $ageInDays = max(1, $createdAt->diffInDays(now()));
            
            // If the plugin is older than the limit, we only take a fraction of its total stats
            $recentRatio = min(1, $daysLimit / $ageInDays);
            
            $approxViews = round($plugin->view_count * $recentRatio);
            $approxStars = round($plugin->star_count * $recentRatio);
            
            if ($approxViews > 0) {
                $this->saveToRedis('views', $plugin->id, $today, $approxViews);
            }
            if ($approxStars > 0) {
                $this->saveToRedis('stars', $plugin->id, $today, $approxStars);
            }
        }
        $this->info("Approximated stats for {$plugins->count()} plugins.");

        // 3. Re-run the refresh command
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
