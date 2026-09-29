<?php

namespace App\Console\Commands;

use App\Contracts\PluginRepositoryInterface;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

#[Signature('plugins:sync-views')]
#[Description('Sync buffered plugin views from Redis to the database')]
class SyncPluginViews extends Command
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
        $bufferKey = config('plugins.views_buffer_key');
        $processingKey = config('plugins.views_buffer_key') . '_processing';

        // Atomically rename the buffer so new incoming views go to a fresh key.
        // If the buffer doesn't exist, rename() throws — we treat that as "nothing to do".
        try {
            Redis::rename($bufferKey, $processingKey);
        } catch (Exception $e) {
            $this->info('No pending plugin views to sync.');

            return;
        }

        $views = Redis::hgetall($processingKey);

        if (empty($views)) {
            Redis::del($processingKey);

            return;
        }

        $this->info('Syncing views for '.count($views).' plugins...');

        $failed = [];

        foreach ($views as $pluginId => $count) {
            try {
                $this->pluginRepository->incrementViewCount($pluginId, (int) $count);
            } catch (Exception $e) {
                // Track failed plugin IDs so we can push them back to Redis
                $failed[$pluginId] = (int) $count;
                $this->error("Failed to sync views for plugin {$pluginId}: {$e->getMessage()}");
            }
        }

        // Push any failed plugin views back into the buffer for the next run
        if (! empty($failed)) {
            foreach ($failed as $pluginId => $count) {
                Redis::hincrby($bufferKey, $pluginId, $count);
            }
            $this->warn(count($failed).' plugin(s) failed to sync. Views pushed back to buffer.');
        }

        // Clean up processing key
        Redis::del($processingKey);

        $synced = count($views) - count($failed);
        $this->info("Done. Synced: {$synced}, Failed: ".count($failed));
    }
}
