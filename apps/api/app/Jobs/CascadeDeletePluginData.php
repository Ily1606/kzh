<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\PluginReport;
use App\Models\Star;
use App\Services\TrendingTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CascadeDeletePluginData implements ShouldQueue
{
    use Queueable;

    public string $pluginId;

    /**
     * Create a new job instance.
     */
    public function __construct(Plugin|string $plugin)
    {
        $this->pluginId = $plugin instanceof Plugin ? $plugin->id : $plugin;
        $this->afterCommit();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $plugin = Plugin::withTrashed()->find($this->pluginId);

        if ($plugin === null || ! $plugin->trashed()) {
            return;
        }

        $deletedAt = $plugin->deleted_at ?? now();
        $chunkSize = $this->getChunkSize();

        // 1. Cascade soft-delete comments
        $this->cascadeDeleteComments($deletedAt, $chunkSize);

        // 2. Cascade soft-delete comment reports
        $this->cascadeDeleteCommentReports($deletedAt, $chunkSize);

        // 3. Cascade soft-delete plugin reports
        $this->cascadeDeletePluginReports($deletedAt, $chunkSize);

        // 4. Cascade soft-delete stars
        $this->cascadeDeleteStars($deletedAt, $chunkSize);

        // 5. Clean up Redis trending caches and timeline cache
        TrendingTracker::cleanupPlugin($this->pluginId);
    }

    /**
     * Soft-delete comments belonging to this plugin in batches.
     */
    private function cascadeDeleteComments(mixed $deletedAt, int $chunkSize): void
    {
        do {
            $commentIds = Comment::where('plugin_id', $this->pluginId)
                ->whereNull('deleted_at')
                ->limit($chunkSize)
                ->pluck('id')
                ->all();

            if (! empty($commentIds)) {
                Comment::whereIn('id', $commentIds)->update(['deleted_at' => $deletedAt]);
            }
        } while (! empty($commentIds));
    }

    /**
     * Soft-delete comment reports belonging to this plugin in batches.
     */
    private function cascadeDeleteCommentReports(mixed $deletedAt, int $chunkSize): void
    {
        do {
            $reportIds = CommentReport::where('plugin_id', $this->pluginId)
                ->whereNull('deleted_at')
                ->limit($chunkSize)
                ->pluck('id')
                ->all();

            if (! empty($reportIds)) {
                CommentReport::whereIn('id', $reportIds)->update(['deleted_at' => $deletedAt]);
            }
        } while (! empty($reportIds));
    }

    /**
     * Soft-delete plugin reports belonging to this plugin in batches.
     */
    private function cascadeDeletePluginReports(mixed $deletedAt, int $chunkSize): void
    {
        do {
            $reportIds = PluginReport::where('plugin_id', $this->pluginId)
                ->whereNull('deleted_at')
                ->limit($chunkSize)
                ->pluck('id')
                ->all();

            if (! empty($reportIds)) {
                PluginReport::whereIn('id', $reportIds)->update(['deleted_at' => $deletedAt]);
            }
        } while (! empty($reportIds));
    }

    /**
     * Soft-delete stars belonging to this plugin in batches.
     */
    private function cascadeDeleteStars(mixed $deletedAt, int $chunkSize): void
    {
        do {
            $userIds = Star::where('plugin_id', $this->pluginId)
                ->whereNull('deleted_at')
                ->limit($chunkSize)
                ->pluck('user_id')
                ->all();

            if (! empty($userIds)) {
                Star::where('plugin_id', $this->pluginId)
                    ->whereIn('user_id', $userIds)
                    ->update(['deleted_at' => $deletedAt]);
            }
        } while (! empty($userIds));
    }

    /**
     * Get the batch chunk size from configuration.
     */
    private function getChunkSize(): int
    {
        return (int) config('plugins.cascade_delete.chunk_size', 500);
    }
}
