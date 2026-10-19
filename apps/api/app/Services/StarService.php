<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Contracts\StarRepositoryInterface;
use App\Events\Star\PluginStarred;
use App\Events\Star\PluginUnstarred;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

final class StarService
{
    public function __construct(
        private readonly StarRepositoryInterface $starRepository,
        private readonly PluginRepositoryInterface $pluginRepository,
    ) {}

    /**
     * Move one user's star on a plugin to `starred`, and report the state that
     * resulted.
     *
     * Set-state rather than toggle: the caller states the outcome it wants, so
     * repeating the request cannot reverse it. A timeout retry or a double tap
     * leaves the plugin exactly where the client intended.
     *
     * @param  RequestContext  $requestContext  Metadata of the originating request, captured at the HTTP boundary so the queued audit listener still sees the real client.
     * @return array{starred: bool, star_count: int}
     */
    public function setStarred(User $user, string $pluginId, bool $starred, RequestContext $requestContext): array
    {
        // Reads before the write: a plugin that is missing or not approved
        // throws here, before anything is touched.
        $plugin = $this->pluginRepository->findApprovedById($pluginId);

        $starCreatedAt = null;
        if ($starred) {
            $changed = $this->starRepository->insertIgnore($plugin->id, $user->getAuthIdentifier());
        } else {
            $starCreatedAt = $this->starRepository->deleteBy($plugin->id, $user->getAuthIdentifier());
            $changed = $starCreatedAt !== null;
        }

        if ($changed) {
            $this->dispatchStarEvent($starred, $plugin->id, $user, $requestContext);
            Cache::forget($this->timelineCacheKey($plugin->id));
            TrendingTracker::trackStar($plugin->id, $starred ? 1 : -1, $starCreatedAt);
            $this->refreshCachedPluginStarCount($plugin->id);
        }

        return [
            'starred' => $starred,
            'star_count' => (int) $plugin->stars()->count(),
        ];
    }

    /**
     * If the plugin is cached in Redis trending objects, update its cached star count.
     */
    private function refreshCachedPluginStarCount(string $pluginId): void
    {
        $hashKey = config('plugins.trending.keys.objects');
        if ($hashKey && Redis::hexists($hashKey, $pluginId)) {
            $fresh = $this->pluginRepository
                ->findApprovedByIds([$pluginId])
                ->first();

            if ($fresh) {
                Redis::hset($hashKey, $pluginId, serialize($fresh));
            }
        }
    }

    /**
     * @param  RequestContext  $requestContext  Metadata of the originating request.
     */
    private function dispatchStarEvent(bool $starred, string $pluginId, User $user, RequestContext $requestContext): void
    {
        $event = $starred ? PluginStarred::class : PluginUnstarred::class;

        $event::dispatch(
            pluginId: $pluginId,
            userId: (string) $user->getAuthIdentifier(),
            requestContext: $requestContext,
        );
    }

    public function getTimeline(string $pluginId)
    {
        return Cache::remember($this->timelineCacheKey($pluginId), config('plugins.star_timeline_cache_ttl'), function () use ($pluginId) {
            $plugin = $this->pluginRepository->findApprovedById($pluginId);
            $dateToCompare = $plugin->approved_at ?? $plugin->created_at;
            $byMonth = $dateToCompare ? $dateToCompare->diffInMonths(now()) > 2 : false;

            return $this->starRepository->getTimeline($pluginId, $byMonth);
        });
    }

    private function timelineCacheKey(string $pluginId): string
    {
        return 'plugin:star_timeline:'.$pluginId;
    }
}
