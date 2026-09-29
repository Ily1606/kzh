<?php

namespace App\Services;

use App\DTOs\PluginViewResult;
use App\Contracts\PluginRepositoryInterface;
use App\Events\Plugin\PluginSubmitted;
use App\Events\Plugin\PluginViewed;
use App\Http\Resources\PluginResource;
use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

final class PluginService
{
    public function __construct(
        private readonly PluginRepositoryInterface $pluginRepository,
    ) {}

    /**
     * @param  User  $user  Submitter the plugin belongs to.
     * @param  array{name: string, title: string, license: string, source_link: string}  $attributes  Validated submission payload.
     * @param  RequestContext  $requestContext  Metadata of the originating request.
     */
    public function submit(User $user, array $attributes, RequestContext $requestContext): Plugin
    {
        try {
            $plugin = $this->pluginRepository->create([
                'name' => $attributes['name'],
                'title' => $attributes['title'],
                'license' => $attributes['license'],
                'source_link' => $attributes['source_link'],
                'user_id' => $user->getAuthIdentifier(),
            ]);

            $plugin->refresh();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('api.plugin_name_already_exists'),
            ]);
        }

        PluginSubmitted::dispatch(
            plugin: $plugin,
            user: $user,
            requestContext: $requestContext,
        );

        return $plugin;
    }

    public function getPaginatedApprovedPlugins(int $perPage): LengthAwarePaginator
    {
        return $this->pluginRepository->getPaginatedApprovedPlugins($perPage);
    }

    /**
     * Increment plugin view count if the user/guest hasn't viewed it in the last 24 hours.
     *
     * - Logged-in users: identified via User ID (Sanctum guard)
     * - Guests: identified via fingerprint (IP + User-Agent)
     * - Redis stores the key with a 24h TTL to prevent view spam
     */
    public function incrementViewIfNotViewed(string $id, string $viewerId): PluginViewResult
    {
        $plugin = $this->pluginRepository->findApprovedById($id);

        $cacheKey = "plugin_view:{$plugin->id}:{$viewerId}";
        $bufferKey = config('plugins.views_buffer_key');
        $ttl = config('plugins.view_cache_ttl');

        if (Cache::add($cacheKey, true, $ttl)) {
            $bufferedViews = (int) Redis::hincrby($bufferKey, $plugin->id, 1);
            return new PluginViewResult(true, $plugin->view_count + $bufferedViews);
        }

        $bufferedViews = (int) Redis::hget($bufferKey, $plugin->id);
        return new PluginViewResult(false, $plugin->view_count + $bufferedViews);
    }

    /**
     * Get trending plugins using the Hacker News Ranking Algorithm.
     *
     * Formula: Score = P / (T + 2)^G
     * - P (Points): Composite score based on views, comments, and stars.
     * - T (Time): Age of the plugin in hours.
     * - G (Gravity): Decay rate. Higher gravity means older items drop faster.
     * - 2 (Age Offset): Prevents division by zero for brand new items.
     *
     * @see https://medium.com/hacking-and-gonzo/how-hacker-news-ranking-algorithm-works-1d9b0cf2c08d
     */
    public function getTrendingPlugins(int $limit): Collection
    {
        $cacheTtl = config('plugins.trending.cache_ttl');
        $daysLimit = config('plugins.trending.days_limit');

        $weightView = (float) config('plugins.trending.weights.view');
        $weightComment = (float) config('plugins.trending.weights.comment');
        $weightStar = (float) config('plugins.trending.weights.star');

        $gravity = (float) config('plugins.trending.gravity');
        $ageOffset = (float) config('plugins.trending.age_offset');

        $cacheKey = "plugins:trending:{$limit}";

        // Determine cache hit/miss
        $result = Cache::get($cacheKey);

        if ($result === null) {
            $weights = [
                'view' => $weightView,
                'comment' => $weightComment,
                'star' => $weightStar,
            ];

            $plugins = $this->pluginRepository->getTrendingPlugins($daysLimit, $weights, $gravity, $ageOffset, $limit);

            // Fallback: If no plugins are found within the last $daysLimit days, retrieve the all-time top plugins
            if ($plugins->isEmpty()) {
                $plugins = $this->pluginRepository->getTopAllTimePlugins($limit);
            }

            $result = $plugins;

            Cache::put($cacheKey, $result, $cacheTtl);
        }

        return $result;
    }
}
