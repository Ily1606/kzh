<?php

namespace App\Jobs;

use App\Contracts\PluginRepositoryInterface;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\Star;
use App\Models\User;
use App\Services\TrendingTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

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
    public function handle(?PluginRepositoryInterface $pluginRepository = null): void
    {
        $pluginRepository ??= app(PluginRepositoryInterface::class);

        $user = User::withTrashed()->find($this->userId);

        if ($user === null || ! $user->trashed()) {
            return;
        }

        $deletedAt = $user->deleted_at ?? now();

        $user->tokens()->delete();

        // 2. Cascade delete user's own plugins (and comments/stars on them)
        $this->cascadeDeleteOwnPlugins($user, $deletedAt);

        // 3. Cascade delete user's comments on other plugins (and their replies)
        $commentPluginIds = $this->cascadeDeleteUserComments($user, $deletedAt);

        // 4. Cascade delete user's stars on other plugins
        $starPluginIds = $this->cascadeDeleteUserStars($user, $deletedAt);

        // 5. Refresh trending cache objects for affected plugins
        $affectedPluginIds = array_values(array_unique(array_merge(
            $commentPluginIds,
            $starPluginIds
        )));

        $this->refreshCachedPluginObjects($affectedPluginIds, $pluginRepository);
    }

    /**
     * Cascade delete plugins owned by the user, including all comments/stars on them
     * and their Redis trending caches.
     */
    private function cascadeDeleteOwnPlugins(User $user, mixed $deletedAt): void
    {
        $ownPluginIds = Plugin::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        if (empty($ownPluginIds)) {
            return;
        }

        $chunkSize = $this->getChunkSize();

        foreach (array_chunk($ownPluginIds, $chunkSize) as $chunk) {
            // Soft-delete plugins and all comments/stars on them
            Plugin::whereIn('id', $chunk)->update(['deleted_at' => $deletedAt]);
            Comment::whereIn('plugin_id', $chunk)->whereNull('deleted_at')->update(['deleted_at' => $deletedAt]);
            Star::whereIn('plugin_id', $chunk)->whereNull('deleted_at')->update(['deleted_at' => $deletedAt]);

            // Clean up Redis trending caches for these plugins
            $this->cleanupPluginTrendingCaches($chunk);
        }
    }

    /**
     * Remove plugins from all Redis trending caches and delete daily stats.
     *
     * @param array<int, string> $pluginIds
     */
    private function cleanupPluginTrendingCaches(array $pluginIds): void
    {
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');
        $daysLimit = (int) config('plugins.trending.days_limit');
        $chunkSize = $this->getChunkSize();

        $pipe = Redis::pipeline();

        if ($zsetKey) {
            $pipe->zrem($zsetKey, ...$pluginIds);
        }
        if ($hashKey) {
            $pipe->hdel($hashKey, ...$pluginIds);
        }
        if ($bufferKey) {
            $pipe->hdel($bufferKey, ...$pluginIds);
        }

        $statKeys = [];
        $interactionTypes = TrendingTracker::INTERACTION_TYPES;

        for ($i = 0; $i < $daysLimit; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $pipe->srem(TrendingTracker::activeKey($date), ...$pluginIds);

            foreach ($pluginIds as $pluginId) {
                foreach ($interactionTypes as $type) {
                    $statKeys[] = TrendingTracker::statKey($type, $date, $pluginId);
                }
            }
        }

        if (! empty($statKeys)) {
            foreach (array_chunk($statKeys, $chunkSize) as $keysChunk) {
                $pipe->del($keysChunk);
            }
        }

        $pipe->exec();
    }

    /**
     * Cascade delete all comments written by the user and all descendant replies,
     * decrementing daily trending stats in Redis.
     *
     * @return array<int, string> Affected plugin IDs
     */
    private function cascadeDeleteUserComments(User $user, mixed $deletedAt): array
    {
        $commentIds = $this->resolveCommentThreadIds($user->id);

        if (empty($commentIds)) {
            return [];
        }

        // 1. Decrement daily Redis trending stats for deleted comments
        $this->decrementCommentTrendingStats($commentIds);

        // 2. Collect affected plugin IDs before soft deleting
        $affectedPluginIds = Comment::whereIn('id', $commentIds)
            ->distinct()
            ->pluck('plugin_id')
            ->all();

        $chunkSize = $this->getChunkSize();

        // 3. Bulk soft-delete comments and replies
        foreach (array_chunk($commentIds, $chunkSize) as $chunk) {
            Comment::whereIn('id', $chunk)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $deletedAt]);
        }

        return $affectedPluginIds;
    }

    /**
     * Recursively resolve all active comment IDs by the user and all their child replies.
     *
     * @return array<int, string>
     */
    private function resolveCommentThreadIds(string $userId): array
    {
        $userCommentIds = Comment::where('author_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        $allCommentIds = $userCommentIds;
        $currentParentIds = $userCommentIds;

        while (! empty($currentParentIds)) {
            $childIds = Comment::whereIn('parent_comment_id', $currentParentIds)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all();

            if (empty($childIds)) {
                break;
            }

            $allCommentIds = array_merge($allCommentIds, $childIds);
            $currentParentIds = $childIds;
        }

        return array_values(array_unique($allCommentIds));
    }

    /**
     * Aggregate visible comments by (plugin_id, date) and decrement Redis trending stats.
     *
     * @param array<int, string> $commentIds
     */
    private function decrementCommentTrendingStats(array $commentIds): void
    {
        $commentStats = Comment::whereIn('id', $commentIds)
            ->whereNull('hidden_at')
            ->selectRaw('plugin_id, DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('plugin_id')
            ->groupByRaw('DATE(created_at)')
            ->get();

        if ($commentStats->isNotEmpty()) {
            TrendingTracker::bulkDecrementInteractions(
                TrendingTracker::INTERACTION_COMMENTS,
                $commentStats->map(fn ($r) => [
                    'plugin_id' => (string) $r->plugin_id,
                    'date' => (string) $r->date,
                    'count' => (int) $r->count,
                ])->all()
            );
        }
    }

    /**
     * Cascade delete all stars given by the user, decrementing daily trending stats in Redis.
     *
     * @return array<int, string> Affected plugin IDs
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
            return [];
        }

        // 1. Decrement daily Redis trending stats for deleted stars
        TrendingTracker::bulkDecrementInteractions(
            TrendingTracker::INTERACTION_STARS,
            $starStats->map(fn ($r) => [
                'plugin_id' => (string) $r->plugin_id,
                'date' => (string) $r->date,
                'count' => (int) $r->count,
            ])->all()
        );

        // 2. Collect affected plugin IDs
        $affectedPluginIds = Star::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('plugin_id')
            ->all();

        // 3. Bulk soft-delete stars
        Star::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $deletedAt]);

        return $affectedPluginIds;
    }

    /**
     * Refresh the cached plugin objects in Redis for the affected plugins.
     *
     * @param array<int, string> $pluginIds
     */
    private function refreshCachedPluginObjects(
        array $pluginIds,
        PluginRepositoryInterface $pluginRepository
    ): void {
        $hashKey = config('plugins.trending.keys.objects');
        if (! $hashKey || empty($pluginIds)) {
            return;
        }

        // Check which plugins actually exist in the trending objects hash in one call
        $cachedFlags = Redis::hmget($hashKey, $pluginIds);
        $cachedPluginIds = [];
        if (is_array($cachedFlags)) {
            foreach ($pluginIds as $index => $pluginId) {
                $val = $cachedFlags[$pluginId] ?? $cachedFlags[$index] ?? null;
                if (! empty($val)) {
                    $cachedPluginIds[] = $pluginId;
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
        return (int) config('plugins.cascade_delete.chunk_size');
    }
}
