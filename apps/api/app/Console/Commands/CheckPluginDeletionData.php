<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\Star;
use App\Services\TrendingTracker;
use Database\Seeders\PluginDeletionTestSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

#[Signature('test:check-plugin-deletion {--process-queue : Process pending queue jobs before inspecting}')]
#[Description('Inspect Database and Redis state for the plugin deletion test scenario')]
class CheckPluginDeletionData extends Command
{
    public function handle(): int
    {
        if ($this->option('process-queue')) {
            $this->info('Processing queue jobs...');
            $this->call('queue:work', ['--stop-when-empty' => true]);
        }

        $targetPlugin = Plugin::withTrashed()->where('name', 'target-plugin-to-delete')->first();

        if (! $targetPlugin) {
            $this->error('Target plugin [target-plugin-to-delete] does not exist. Run seeder first:');
            $this->line('  php artisan db:seed --class=PluginDeletionTestSeeder');

            return self::FAILURE;
        }

        $otherPlugin = Plugin::withTrashed()->where('name', 'other-community-plugin')->first();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        $zsetKey = config('plugins.trending.keys.zset', 'plugins:trending_zset');
        $hashKey = config('plugins.trending.keys.objects', 'plugins:trending_objects');
        $bufferKey = config('plugins.views_buffer_key', 'plugins:views_buffer');
        $processingKey = $bufferKey.'_processing';

        $this->info('================================================================');
        $this->info('          PLUGIN DELETION STATUS INSPECTOR                      ');
        $this->info('================================================================');

        // 1. Target Plugin & Database Cascades
        $activeComments = Comment::where('plugin_id', $targetPlugin->id)->count();
        $totalComments = Comment::withTrashed()->where('plugin_id', $targetPlugin->id)->count();
        $activeReports = CommentReport::where('plugin_id', $targetPlugin->id)->count();
        $totalReports = CommentReport::withTrashed()->where('plugin_id', $targetPlugin->id)->count();
        $activeStars = Star::where('plugin_id', $targetPlugin->id)->count();
        $totalStars = Star::withTrashed()->where('plugin_id', $targetPlugin->id)->count();

        $this->line('<fg=yellow;options=bold>1. TARGET PLUGIN DATABASE STATE</>');
        $this->table(
            ['Check Item', 'Current Value', 'Status'],
            [
                ['Plugin ID', $targetPlugin->id, ''],
                ['Plugin Name', $targetPlugin->name, ''],
                ['Status', $targetPlugin->status->value, $targetPlugin->trashed() ? '<fg=green>DELETED (OK)</>' : '<fg=yellow>ACTIVE (NOT DELETED YET)</>'],
                ['Deleted At', (string) $targetPlugin->deleted_at, $targetPlugin->trashed() ? '<fg=green>SET (OK)</>' : '<fg=yellow>NULL</>'],
                ['Active Comments Count', "{$activeComments} / {$totalComments}", $activeComments === 0 ? '<fg=green>0 (CLEANED UP)</>' : "<fg=yellow>{$activeComments} ACTIVE</>"],
                ['Active Reports Count', "{$activeReports} / {$totalReports}", $activeReports === 0 ? '<fg=green>0 (CLEANED UP)</>' : "<fg=yellow>{$activeReports} ACTIVE</>"],
                ['Active Stars Count', "{$activeStars} / {$totalStars}", $activeStars === 0 ? '<fg=green>0 (CLEANED UP)</>' : "<fg=yellow>{$activeStars} ACTIVE</>"],
            ]
        );

        // 2. Target Plugin Redis & Cache
        $inZset = Redis::zscore($zsetKey, $targetPlugin->id) !== null && Redis::zscore($zsetKey, $targetPlugin->id) !== false;
        $inObjects = (bool) Redis::hexists($hashKey, $targetPlugin->id);
        $inBuffer = (bool) Redis::hexists($bufferKey, $targetPlugin->id);
        $inProcessing = (bool) Redis::hexists($processingKey, $targetPlugin->id);
        $inActiveToday = (bool) Redis::sismember(TrendingTracker::activeKey($today), $targetPlugin->id);
        $inActiveYesterday = (bool) Redis::sismember(TrendingTracker::activeKey($yesterday), $targetPlugin->id);

        $viewsStatToday = Redis::get(TrendingTracker::statKey('views', $today, $targetPlugin->id));
        $viewsStatYesterday = Redis::get(TrendingTracker::statKey('views', $yesterday, $targetPlugin->id));
        $commentsStatToday = Redis::get(TrendingTracker::statKey('comments', $today, $targetPlugin->id));
        $starsStatToday = Redis::get(TrendingTracker::statKey('stars', $today, $targetPlugin->id));

        $timelineCache1 = Cache::get("plugin:star_timeline:{$targetPlugin->id}");
        $timelineCache2 = Cache::get("plugins:{$targetPlugin->id}:star_timeline");

        $this->newLine();
        $this->line('<fg=yellow;options=bold>2. TARGET PLUGIN REDIS & CACHE STATE</>');
        $this->table(
            ['Storage', 'Key / Item', 'Current Value', 'Status'],
            [
                ['Redis', "ZSET ({$zsetKey})", $inZset ? 'Present' : 'Not Found', $inZset ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                ['Redis', "Objects Hash ({$hashKey})", $inObjects ? 'Present' : 'Not Found', $inObjects ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                ['Redis', "Views Buffer ({$bufferKey})", $inBuffer ? 'Present' : 'Not Found', $inBuffer ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                ['Redis', "Processing Buffer ({$processingKey})", $inProcessing ? 'Present' : 'Not Found', $inProcessing ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                ['Redis', "Active Set ({$today})", $inActiveToday ? 'Present' : 'Not Found', $inActiveToday ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                ['Redis', "Active Set ({$yesterday})", $inActiveYesterday ? 'Present' : 'Not Found', $inActiveYesterday ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                ['Redis', 'Daily Views Stat (Today)', $viewsStatToday ?? 'null', $viewsStatToday !== null ? "<fg=yellow>{$viewsStatToday}</>" : '<fg=green>DELETED (OK)</>'],
                ['Redis', 'Daily Views Stat (Yesterday)', $viewsStatYesterday ?? 'null', $viewsStatYesterday !== null ? "<fg=yellow>{$viewsStatYesterday}</>" : '<fg=green>DELETED (OK)</>'],
                ['Redis', 'Daily Comments Stat (Today)', $commentsStatToday ?? 'null', $commentsStatToday !== null ? "<fg=yellow>{$commentsStatToday}</>" : '<fg=green>DELETED (OK)</>'],
                ['Redis', 'Daily Stars Stat (Today)', $starsStatToday ?? 'null', $starsStatToday !== null ? "<fg=yellow>{$starsStatToday}</>" : '<fg=green>DELETED (OK)</>'],
                ['Cache', "Timeline (plugin:star_timeline:{$targetPlugin->id})", $timelineCache1 ? 'Cached' : 'Null', $timelineCache1 ? '<fg=yellow>CACHED</>' : '<fg=green>CLEARED (OK)</>'],
                ['Cache', "Timeline (plugins:{$targetPlugin->id}:star_timeline)", $timelineCache2 ? 'Cached' : 'Null', $timelineCache2 ? '<fg=yellow>CACHED</>' : '<fg=green>CLEARED (OK)</>'],
            ]
        );

        // 3. Other Plugin Isolation State
        if ($otherPlugin) {
            $otherInZset = Redis::zscore($zsetKey, $otherPlugin->id) !== null && Redis::zscore($zsetKey, $otherPlugin->id) !== false;
            $otherInObjects = (bool) Redis::hexists($hashKey, $otherPlugin->id);
            $otherComments = Comment::where('plugin_id', $otherPlugin->id)->count();
            $otherStars = Star::where('plugin_id', $otherPlugin->id)->count();

            $this->newLine();
            $this->line('<fg=yellow;options=bold>3. OTHER PLUGIN (ISOLATION VERIFICATION)</>');
            $this->table(
                ['Check Item', 'Current Value', 'Status'],
                [
                    ['Plugin Name', $otherPlugin->name, ''],
                    ['Soft-Deleted', $otherPlugin->trashed() ? 'Yes' : 'No', ! $otherPlugin->trashed() ? '<fg=green>LIVE (PRESERVED OK)</>' : '<fg=red>DELETED BY ACCIDENT</>'],
                    ['Active Comments', (string) $otherComments, $otherComments > 0 ? '<fg=green>INTACT (OK)</>' : '<fg=red>AFFECTED</>'],
                    ['Active Stars', (string) $otherStars, $otherStars > 0 ? '<fg=green>INTACT (OK)</>' : '<fg=red>AFFECTED</>'],
                    ['In Trending ZSET', $otherInZset ? 'Yes' : 'No', $otherInZset ? '<fg=green>PRESERVED (OK)</>' : '<fg=red>REMOVED BY ACCIDENT</>'],
                    ['In Objects Hash', $otherInObjects ? 'Yes' : 'No', $otherInObjects ? '<fg=green>PRESERVED (OK)</>' : '<fg=red>REMOVED BY ACCIDENT</>'],
                ]
            );
        }

        if ($targetPlugin->trashed() && ($activeComments > 0 || $activeStars > 0 || $inZset)) {
            $this->newLine();
            $this->warn('⚠️  NOTICE: The plugin has been soft-deleted, but related child data/Redis metrics are still active because the cascade job runs asynchronously via queue (Queue: ' . config('queue.default') . ').');
            $this->line('   If a queue worker is not running, the [CascadeDeletePluginData] job is waiting in the Redis queue.');
            $this->line('   👉 Run the queue worker to process pending jobs:');
            $this->line('      <fg=cyan>php artisan queue:work --stop-when-empty</>');
            $this->line('   👉 Or re-run this command with the --process-queue flag:');
            $this->line('      <fg=cyan>php artisan test:check-plugin-deletion --process-queue</>');
        }

        $this->newLine();
        $this->info('Inspection complete.');

        return self::SUCCESS;
    }
}

