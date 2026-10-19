<?php

namespace App\Console\Commands;

use App\Contracts\PluginRepositoryInterface;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
        $processingKey = $bufferKey.'_processing';

        // Process any leftover processing key from a previous interrupted run
        if (Redis::exists($processingKey)) {
            $this->info('Found leftover processing key. Syncing it first...');
            $this->processBuffer($processingKey, $bufferKey);
        }

        // Check if there's anything new to process
        if (! Redis::exists($bufferKey)) {
            $this->info('No pending plugin views to sync.');

            return;
        }

        // Rename the buffer so new incoming views go to a fresh key.
        Redis::rename($bufferKey, $processingKey);

        $this->processBuffer($processingKey, $bufferKey);

        $this->info('Done.');
    }

    private function processBuffer(string $processingKey, string $bufferKey): void
    {
        $views = Redis::hgetall($processingKey);

        if (empty($views)) {
            Redis::del($processingKey);

            return;
        }

        $this->info('Syncing views for '.count($views).' plugins...');

        $failedCount = 0;

        foreach ($views as $pluginId => $count) {
            try {
                $this->pluginRepository->incrementViewCount($pluginId, (int) $count);
                // HDEL immediately after successful increment to prevent double counting if killed mid-loop
                Redis::hdel($processingKey, $pluginId);
            } catch (Exception $e) {
                $failedCount++;
                Log::error("Failed to sync views for plugin {$pluginId}", [
                    'exception' => $e,
                ]);
                $this->error("Failed to sync views for plugin {$pluginId}: {$e->getMessage()}");

                // Push failed plugin views back into the buffer for the next run
                Redis::hincrby($bufferKey, $pluginId, (int) $count);
                Redis::hdel($processingKey, $pluginId);
            }
        }

        if ($failedCount > 0) {
            $this->warn("{$failedCount} plugin(s) failed to sync. Views pushed back to buffer.");
        }

        // Clean up processing key
        Redis::del($processingKey);
    }
}
