<?php

namespace App\Jobs;

use App\Services\TrendingTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshTrendingCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $pluginIds
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            TrendingTracker::refreshPlugins($this->pluginIds);
        } catch (\Exception $e) {
            Log::error('Retry failed for trending cache refresh', [
                'plugin_ids' => $this->pluginIds,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger queue retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Trending cache refresh job failed permanently', [
            'plugin_ids' => $this->pluginIds,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);
    }
}