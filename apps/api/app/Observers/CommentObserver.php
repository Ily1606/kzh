<?php

namespace App\Observers;

use App\Contracts\PluginRepositoryInterface;
use App\Models\Comment;
use App\Services\TrendingTracker;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CommentObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Dispatch observer events only after database transactions commit.
     */
    public bool $afterCommit = true;

    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        // Only track visible comments (skip moderated/hidden on creation)
        if ($comment->hidden_at === null) {
            try {
                // 1. Increment daily comment interaction count in Redis
                TrendingTracker::trackComment($comment->plugin_id, 1, $comment->created_at);

                // 2. If the plugin is currently cached in trending objects, update its cached comment count
                $this->refreshCachedPluginCommentCount($comment->plugin_id);
            } catch (Throwable $e) {
                Log::warning("Failed to track comment creation in Redis for comment {$comment->id}: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Handle the Comment "deleted" event (soft delete or force delete).
     */
    public function deleted(Comment $comment): void
    {
        // Hidden comments were already subtracted when hidden; a force delete of an
        // already-trashed comment was already subtracted at soft-delete time.
        $wasAlreadyTrashed = $comment->isForceDeleting() && $comment->getOriginal('deleted_at') !== null;

        if ($comment->hidden_at === null && ! $wasAlreadyTrashed) {
            $this->handleCommentDeleted($comment);
        }
    }

    /**
     * Handle the Comment "updated" event (e.g. moderation hide/unhide).
     */
    public function updated(Comment $comment): void
    {
        if ($comment->wasChanged('hidden_at') && ! $comment->trashed()) {
            if ($comment->hidden_at !== null) {
                // Ẩn comment: trừ điểm trending và cập nhật Redis
                $this->handleCommentDeleted($comment);
            } else {
                // Bỏ ẩn comment: cộng lại điểm trending và cập nhật Redis
                $this->handleCommentRestored($comment);
            }
        }
    }

    /**
     * Handle the Comment "restored" event (un-delete).
     */
    public function restored(Comment $comment): void
    {
        if ($comment->hidden_at === null) {
            $this->handleCommentRestored($comment);
        }
    }

    /**
     * Decrement trending interaction and refresh cached comment count in Redis.
     */
    private function handleCommentDeleted(Comment $comment): void
    {
        try {
            // 1. Decrement daily comment interaction count in Redis
            TrendingTracker::trackComment($comment->plugin_id, -1, $comment->created_at);

            // 2. If the plugin is currently cached in trending objects, update its cached comment count
            $this->refreshCachedPluginCommentCount($comment->plugin_id);
        } catch (Throwable $e) {
            Log::warning("Failed to track comment deletion/hide in Redis for comment {$comment->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Re-increment trending interaction and refresh cached comment count in Redis.
     */
    private function handleCommentRestored(Comment $comment): void
    {
        try {
            // 1. Re-increment daily comment interaction count in Redis
            TrendingTracker::trackComment($comment->plugin_id, 1, $comment->created_at);

            // 2. If the plugin is currently cached in trending objects, update its cached comment count
            $this->refreshCachedPluginCommentCount($comment->plugin_id);
        } catch (Throwable $e) {
            Log::warning("Failed to track comment restore/unhide in Redis for comment {$comment->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * If the plugin is cached in Redis trending objects, refresh its cached comment count.
     */
    private function refreshCachedPluginCommentCount(string $pluginId): void
    {
        try {
            $hashKey = config('plugins.trending.keys.objects');
            if ($hashKey && Redis::hexists($hashKey, $pluginId)) {
                $fresh = app(PluginRepositoryInterface::class)
                    ->findApprovedByIds([$pluginId])
                    ->first();

                if ($fresh) {
                    Redis::hset($hashKey, $pluginId, serialize($fresh));
                }
            }
        } catch (Throwable $e) {
            Log::warning("Failed to refresh cached plugin comment count in Redis for plugin {$pluginId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
