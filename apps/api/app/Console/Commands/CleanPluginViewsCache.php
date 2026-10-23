<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use App\Models\PluginDailyView;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

#[Signature('plugins:clean-views-cache {--all : Clean buffer, view deduplication cache, and trending view stats} {--reset-db : Also reset view_count to 0 and truncate plugin_daily_views table}')]
#[Description('Clean Redis views buffer and anti-spam cache to cleanly re-test sync-views')]
class CleanPluginViewsCache extends Command
{
    public function handle(): int
    {
        $bufferKey = config('plugins.views_buffer_key', 'plugins:views_buffer');
        $processingKey = $bufferKey.'_processing';
        $prefix = (string) config('database.redis.options.prefix');

        $this->info('Cleaning Redis views cache...');

        // 1. Clean views buffer & processing buffer
        Redis::del($bufferKey);
        Redis::del($processingKey);
        $this->line("  ✓ Deleted buffer: <fg=cyan>{$bufferKey}</>");
        $this->line("  ✓ Deleted processing buffer: <fg=cyan>{$processingKey}</>");

        // 2. Clean anti-spam view deduplication cache (plugin_view:*)
        /** @var array<int, string> $dedupKeys */
        $dedupKeys = (array) Redis::command('keys', ['*plugin_view:*']);
        $deletedDedup = 0;
        foreach ($dedupKeys as $key) {
            $unprefixed = str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
            Redis::del($unprefixed);
            $deletedDedup++;
        }
        $this->line("  ✓ Deleted {$deletedDedup} view deduplication key(s) (anti-spam 24h cache).");

        // Flush application cache store as well
        Cache::flush();
        $this->line('  ✓ Flushed Laravel Cache store.');

        // 3. Optional: Clean trending view stats
        if ($this->option('all')) {
            /** @var array<int, string> $viewStatKeys */
            $viewStatKeys = (array) Redis::command('keys', ['*trending:stats:views:*']);
            $deletedStats = 0;
            foreach ($viewStatKeys as $key) {
                $unprefixed = str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
                Redis::del($unprefixed);
                $deletedStats++;
            }
            $this->line("  ✓ Deleted {$deletedStats} trending view daily stat key(s).");
        }

        // 4. Optional: Reset Database counts
        if ($this->option('reset-db')) {
            if ($this->confirm('Are you sure you want to reset all plugin view counts to 0 and truncate plugin_daily_views table?', true)) {
                PluginDailyView::truncate();
                Plugin::query()->update(['view_count' => 0]);
                $this->line('  ✓ Truncated table: <fg=cyan>plugin_daily_views</>');
                $this->line('  ✓ Reset <fg=cyan>view_count = 0</> on all plugins in database.');
            }
        }

        $this->newLine();
        $this->info('Views cache cleaned successfully! You can now cleanly test:');
        $this->line('  1. Call track view API (POST /api/v1/plugins/{id}/view)');
        $this->line('  2. Check buffer (php artisan tinker --execute="dump(Illuminate\Support\Facades\Redis::hgetall(\'plugins:views_buffer\'));")');
        $this->line('  3. Run sync (php artisan plugins:sync-views)');

        return self::SUCCESS;
    }
}
