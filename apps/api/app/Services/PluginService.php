<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Contracts\StarRepositoryInterface;
use App\Events\Plugin\PluginSubmitted;
use App\Events\Plugin\PluginViewed;
use App\Http\Resources\PluginResource;
use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Auth\Access\AuthorizationException;
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
        private readonly StarRepositoryInterface $starRepository,
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

    /**
     * Apply a partial edit to a plugin the caller owns.
     *
     * @param  array<string, mixed>  $attributes  Validated patch payload.
     *
     * @throws AuthorizationException the plugin belongs to another user.
     * @throws ValidationException the new name is already taken by this user.
     */
    public function update(User $user, string $pluginId, array $attributes): Plugin
    {
        $plugin = $this->pluginRepository->findById($pluginId);

        if ($plugin->user_id !== $user->getAuthIdentifier()) {
            throw new AuthorizationException;
        }

        try {
            $updated = $this->pluginRepository->update($plugin, $attributes);
        } catch (UniqueConstraintViolationException) {
            // Same shape as submit(): a name already held by this user comes
            // back as a field-level validation error, not a 500. Two requests
            // renaming to the same value at once both pass validation and one
            // loses here — the same trade-off submit() already makes.
            throw ValidationException::withMessages([
                'name' => [__('api.plugin_name_already_exists')],
            ]);
        }

        return $updated;
    }

    /**
     * @param  User  $user  Signed-in viewer, or null for a guest. Drives whether
     *                      each plugin carries the viewer-specific `is_star` flag.
     */
    public function getPaginatedApprovedPlugins(int $perPage, ?User $user = null): LengthAwarePaginator
    {
        $paginator = $this->pluginRepository->getPaginatedApprovedPlugins($perPage);

        // A guest has no stars, so there is nothing to resolve and no flag to
        // set. Skipping here is also what keeps the public list query count at
        // its old level for anonymous callers.
        if ($user === null) {
            return $paginator;
        }

        $plugins = $paginator->items();

        // One query for the whole page instead of one per plugin. Only the ids
        // on this page are sent, so the result set stays the size of the page.
        $starredIds = $this->starRepository->starredPluginIds(
            array_map(static fn (Plugin $plugin): string => $plugin->id, $plugins),
            (string) $user->getAuthIdentifier(),
        );

        // `is_star` is a transient attribute, not a column: PluginResource reads
        // it when present and omits the field otherwise (see PluginResource).
        foreach ($plugins as $plugin) {
            $plugin->is_star = in_array($plugin->id, $starredIds, true);
        }

        return $paginator;
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

        if (! $cacheHit) {
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
