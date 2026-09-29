<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Events\Plugin\PaginatedPluginsFetched;
use App\Events\Plugin\PluginSubmitted;
use App\Events\Plugin\PluginViewed;
use App\Events\Plugin\TrendingPluginsFetched;
use App\Http\Resources\PluginResource;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
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
     * @param  array{name: string, title: string, license: string, source_link: string}  $attributes
     */
    public function submit(User $user, array $attributes): Plugin
    {
        try {
            $plugin = $this->pluginRepository->create([
                'name' => $attributes['name'],
                'title' => $attributes['title'],
                'license' => $attributes['license'],
                'source_link' => $attributes['source_link'],
                'user_id' => $user->getAuthIdentifier(),
                'status' => PluginStatus::Pending,
                'star_count' => 0,
                'comment_count' => 0,
                'view_count' => 0,
                'approved_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('api.plugin_name_already_exists'),
            ]);
        }

        PluginSubmitted::dispatch(
            plugin: $plugin,
            user: $user,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
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
    public function incrementViewIfNotViewed(string $id, Request $request): array
    {
        $plugin = $this->pluginRepository->findApprovedById($id);

        $viewerId = $request->user('sanctum')?->id ?? $request->fingerprint();

        $cacheKey = "plugin_view:{$plugin->id}:{$viewerId}";

        // Retrieve TTL from configuration (default 86400 seconds / 24 hours)
        $ttl = config('plugins.view_cache_ttl');

        // Use Cache::add for atomic operation to prevent race conditions (VIEW-13)
        if (Cache::add($cacheKey, true, $ttl)) {
            // Buffer the view count in Redis instead of hitting DB directly (avoid locking bottleneck)
            Redis::hincrby('plugins:views_buffer', $plugin->id, 1);

            PluginViewed::dispatch($plugin, (string) $viewerId);

            // Calculate estimated real-time view count for the API response
            $bufferedViews = (int) Redis::hget('plugins:views_buffer', $plugin->id);

            return [
                'status' => 'success',
                'message' => __('api.plugin_view_counted'),
                'view_count' => $plugin->view_count + $bufferedViews,
            ];
        }

        // Add any buffered views to the current DB count so the user sees the latest estimated total
        $bufferedViews = (int) Redis::hget('plugins:views_buffer', $plugin->id);

        return [
            'status' => 'ignored',
            'message' => __('api.plugin_view_already_counted'),
            'view_count' => $plugin->view_count + $bufferedViews,
        ];
    }

    /**
     * Get trending plugins using the Hacker News Ranking Algorithm.
     *
     * Formula: Score = (P - 1) / (T + 2)^G
     * - P (Points): Composite score based on views, comments, and stars.
     * - T (Time): Age of the plugin in hours.
     * - G (Gravity): Decay rate. Higher gravity means older items drop faster.
     * - 2 (Age Offset): Prevents division by zero for brand new items.
     *
     * @see https://medium.com/hacking-and-gonzo/how-hacker-news-ranking-algorithm-works-1d9b0cf2c08d
     *
     * @param int $limit
     * @return array
     */
    public function getTrendingPlugins(int $limit): array
    {
        $cacheTtl = config('plugins.trending.cache_ttl');
        $daysLimit = config('plugins.trending.days_limit');

        $weightView = (float) config('plugins.trending.weights.view');
        $weightComment = (float) config('plugins.trending.weights.comment');
        $weightStar = (float) config('plugins.trending.weights.star');

        $gravity = (float) config('plugins.trending.gravity');
        $ageOffset = (float) config('plugins.trending.age_offset');

        $cacheKey = "plugins:trending:{$limit}";

        // Use Cache::get first to atomically determine cache hit/miss
        $result = Cache::get($cacheKey);
        $cacheHit = $result !== null;

        if (!$cacheHit) {
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

            // Resolve resource to array BEFORE caching to avoid Eloquent serialization issues
            $result = PluginResource::collection($plugins)->resolve();

            Cache::put($cacheKey, $result, $cacheTtl);
        }

        return $result;
    }
}
