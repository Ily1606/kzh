<?php

namespace App\Jobs;

use App\Contracts\PluginRepositoryInterface;
use App\Mail\AccountDeletedMail;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\PluginReport;
use App\Models\Star;
use App\Models\User;
use App\Services\StarService;
use App\Services\TrendingTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CascadeDeleteUserData implements ShouldQueue
{
    use Queueable;

    public string $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(User|string $user)
    {
        $this->userId = $user instanceof User ? $user->id : $user;
        $this->afterCommit();
    }

    /**
     * Execute the job.
     */
    public function handle(PluginRepositoryInterface $pluginRepository): void
    {
        $user = User::withTrashed()->find($this->userId);

        if ($user === null || ! $user->trashed()) {
            return;
        }

        $deletedAt = $user->deleted_at ?? now();

        // 1. Perform all database cascade soft-deletes atomically in a single transaction.
        // Collect exact stats and IDs that were actually soft-deleted for post-commit processing.
        $deletionData = DB::transaction(function () use ($user, $deletedAt) {
            $this->revokeTokens($user);

            $ownPluginIds = $this->cascadeDeleteOwnPlugins($user, $deletedAt);

            $commentDeletion = $this->cascadeDeleteUserComments($user, $deletedAt);

            $starDeletion = $this->cascadeDeleteUserStars($user, $deletedAt);

            $this->cascadeDeleteUserReports($user, $deletedAt);

            $affectedPluginIds = array_values(array_unique(array_merge(
                $commentDeletion['affectedPluginIds'],
                $starDeletion['affectedPluginIds']
            )));

            return [
                'ownPluginIds' => $ownPluginIds,
                'commentStats' => $commentDeletion['stats'],
                'starStats' => $starDeletion['stats'],
                'affectedPluginIds' => $affectedPluginIds,
            ];
        });

        // 2. Perform Redis and Cache cleanup operations post-commit.
        // This ensures atomicity and idempotency: if the DB transaction rolled back, Redis is untouched.
        // If the job retries, already-deleted records will not be found, so stats won't be decremented again.

        // 2a. Clean up Redis trending caches for user's own plugins
        if (! empty($deletionData['ownPluginIds'])) {
            $chunkSize = $this->getChunkSize();
            foreach (array_chunk($deletionData['ownPluginIds'], $chunkSize) as $chunk) {
                TrendingTracker::cleanupPlugins($chunk, true);
            }
        }

        // 2b. Decrement daily Redis trending stats for deleted comments on other plugins
        if (! empty($deletionData['commentStats'])) {
            TrendingTracker::bulkDecrementInteractions(
                TrendingTracker::INTERACTION_COMMENTS,
                $deletionData['commentStats']
            );
        }

        // 2c. Decrement daily Redis trending stats for deleted stars on other plugins
        if (! empty($deletionData['starStats'])) {
            TrendingTracker::bulkDecrementInteractions(
                TrendingTracker::INTERACTION_STARS,
                $deletionData['starStats']
            );
        }

        // 2d. Flush star timeline caches for affected external plugins
        foreach ($deletionData['affectedPluginIds'] as $pluginId) {
            Cache::forget(StarService::timelineCacheKey($pluginId));
        }

        // 2e. Refresh trending cache objects for affected plugins
        $this->refreshCachedPluginObjects($deletionData['affectedPluginIds'], $pluginRepository);

        // 3. Send account deletion notification email to user
        $this->sendAccountDeletedEmail($user);
    }

    /**
     * Revoke all personal access tokens for the user.
     */
    private function revokeTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Send an email notifying the user that their account has been deleted.
     */
    private function sendAccountDeletedEmail(User $user): void
    {
        if (empty($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new AccountDeletedMail($user));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Cascade soft-delete plugins owned by the user, including all comments/stars on them.
     *
     * @return array<int, string> The owned plugin IDs that were soft-deleted
     */
    private function cascadeDeleteOwnPlugins(User $user, mixed $deletedAt): array
    {
        $ownPluginIds = Plugin::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        if (empty($ownPluginIds)) {
            return [];
        }

        $chunkSize = $this->getChunkSize();

        foreach (array_chunk($ownPluginIds, $chunkSize) as $chunk) {
            Plugin::whereIn('id', $chunk)->update(['deleted_at' => $deletedAt]);
            Comment::whereIn('plugin_id', $chunk)->whereNull('deleted_at')->update(['deleted_at' => $deletedAt]);
            CommentReport::whereIn('plugin_id', $chunk)->whereNull('deleted_at')->update(['deleted_at' => $deletedAt]);
            PluginReport::whereIn('plugin_id', $chunk)->whereNull('deleted_at')->update(['deleted_at' => $deletedAt]);
            Star::whereIn('plugin_id', $chunk)->whereNull('deleted_at')->update(['deleted_at' => $deletedAt]);
        }

        return $ownPluginIds;
    }

    /**
     * Cascade soft-delete all comments written by the user and all descendant replies.
     *
     * @return array{affectedPluginIds: array<int, string>, stats: array<int, array{plugin_id: string, date: string, count: int}>}
     */
    private function cascadeDeleteUserComments(User $user, mixed $deletedAt): array
    {
        $commentIds = $this->resolveCommentThreadIds($user->id);

        if (empty($commentIds)) {
            return [
                'affectedPluginIds' => [],
                'stats' => [],
            ];
        }

        $chunkSize = $this->getChunkSize();

        // 1. Collect affected plugin IDs in chunks before soft deleting
        $affectedPluginIds = [];
        foreach (array_chunk($commentIds, $chunkSize) as $chunk) {
            $pluginIds = Comment::whereIn('id', $chunk)
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('plugin_id')
                ->all();

            if (! empty($pluginIds)) {
                $affectedPluginIds = array_merge($affectedPluginIds, $pluginIds);
            }
        }
        $affectedPluginIds = array_values(array_unique($affectedPluginIds));

        // 2. Aggregate visible comments by (plugin_id, date) in chunks for post-commit Redis stats decrement
        $statsMap = [];
        foreach (array_chunk($commentIds, $chunkSize) as $chunk) {
            $chunkStats = Comment::whereIn('id', $chunk)
                ->whereNull('deleted_at')
                ->whereNull('hidden_at')
                ->selectRaw('plugin_id, DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('plugin_id')
                ->groupByRaw('DATE(created_at)')
                ->get();

            foreach ($chunkStats as $r) {
                $key = "{$r->plugin_id}:{$r->date}";
                if (! isset($statsMap[$key])) {
                    $statsMap[$key] = [
                        'plugin_id' => (string) $r->plugin_id,
                        'date' => (string) $r->date,
                        'count' => 0,
                    ];
                }
                $statsMap[$key]['count'] += (int) $r->count;
            }
        }
        $stats = array_values($statsMap);

        // 3. Bulk soft-delete comments and replies in chunks
        foreach (array_chunk($commentIds, $chunkSize) as $chunk) {
            Comment::whereIn('id', $chunk)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $deletedAt]);

            CommentReport::whereIn('comment_id', $chunk)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $deletedAt]);
        }

        return [
            'affectedPluginIds' => $affectedPluginIds,
            'stats' => $stats,
        ];
    }

    /**
     * Recursively resolve all active comment IDs by the user and all their child replies.
     *
     * @return array<int, string>
     */
    private function resolveCommentThreadIds(string $userId): array
    {
        $chunkSize = $this->getChunkSize();

        $userCommentIds = Comment::where('author_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        $allCommentIds = $userCommentIds;
        $currentParentIds = $userCommentIds;

        while (! empty($currentParentIds)) {
            $nextChildIds = [];

            foreach (array_chunk($currentParentIds, $chunkSize) as $parentChunk) {
                $childIds = Comment::whereIn('parent_comment_id', $parentChunk)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->all();

                if (! empty($childIds)) {
                    $nextChildIds = array_merge($nextChildIds, $childIds);
                }
            }

            if (empty($nextChildIds)) {
                break;
            }

            $allCommentIds = array_merge($allCommentIds, $nextChildIds);
            $currentParentIds = $nextChildIds;
        }

        return array_values(array_unique($allCommentIds));
    }

    /**
     * Cascade soft-delete all stars given by the user, returning stats for post-commit Redis decrement.
     *
     * @return array{affectedPluginIds: array<int, string>, stats: array<int, array{plugin_id: string, date: string, count: int}>}
     */
    private function cascadeDeleteUserStars(User $user, mixed $deletedAt): array
    {
        $starStats = Star::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->selectRaw('plugin_id, DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('plugin_id')
            ->groupByRaw('DATE(created_at)')
            ->get();

        if ($starStats->isEmpty()) {
            return [
                'affectedPluginIds' => [],
                'stats' => [],
            ];
        }

        // 1. Collect affected plugin IDs
        $affectedPluginIds = Star::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('plugin_id')
            ->all();

        $stats = $starStats->map(fn ($r) => [
            'plugin_id' => (string) $r->plugin_id,
            'date' => (string) $r->date,
            'count' => (int) $r->count,
        ])->all();

        // 2. Bulk soft-delete stars
        Star::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $deletedAt]);

        return [
            'affectedPluginIds' => $affectedPluginIds,
            'stats' => $stats,
        ];
    }

    /**
     * Cascade delete all reports created by the user.
     */
    private function cascadeDeleteUserReports(User $user, mixed $deletedAt): void
    {
        CommentReport::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $deletedAt]);

        PluginReport::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $deletedAt]);
    }

    /**
     * Refresh the cached plugin objects in Redis for the affected plugins.
     *
     * @param  array<int, string>  $pluginIds
     */
    private function refreshCachedPluginObjects(
        array $pluginIds,
        PluginRepositoryInterface $pluginRepository
    ): void {
        $hashKey = config('plugins.trending.keys.objects');
        if (! $hashKey || empty($pluginIds)) {
            return;
        }

        // Check which plugins actually exist in the trending objects hash in chunks
        $cachedPluginIds = [];
        $chunkSize = $this->getChunkSize();

        foreach (array_chunk($pluginIds, $chunkSize) as $chunk) {
            $cachedFlags = Redis::hmget($hashKey, $chunk);
            if (is_array($cachedFlags)) {
                foreach ($chunk as $index => $pluginId) {
                    $val = $cachedFlags[$pluginId] ?? $cachedFlags[$index] ?? null;
                    if (! empty($val)) {
                        $cachedPluginIds[] = $pluginId;
                    }
                }
            }
        }

        if (empty($cachedPluginIds)) {
            return;
        }

        $freshPlugins = $pluginRepository->findApprovedByIds($cachedPluginIds);

        $pipe = Redis::pipeline();
        foreach ($freshPlugins as $fresh) {
            $pipe->hset($hashKey, $fresh->id, serialize($fresh));
        }
        $pipe->exec();
    }

    /**
     * Get the batch chunk size from configuration.
     */
    private function getChunkSize(): int
    {
        return max(1, (int) config('plugins.cascade_delete.chunk_size', 500));
    }
}
