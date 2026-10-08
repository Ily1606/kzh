<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use App\DTOs\PluginViewResult;
use App\Events\Plugin\PluginSubmitted;
use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
use App\Contracts\StarRepositoryInterface;
use App\Events\Plugin\PluginUpdated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
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
     * @param  RequestContext  $requestContext  Metadata of the originating request.
     *
     * @throws AuthorizationException the plugin belongs to another user.
     * @throws ValidationException the new name is already taken by this user.
     */
    public function update(User $user, string $pluginId, array $attributes, RequestContext $requestContext): Plugin
    {
        $plugin = $this->pluginRepository->findById($pluginId);

        if ($plugin->user_id !== $user->getAuthIdentifier()) {
            throw new AuthorizationException;
        }

        // Read before the write, because update() ends in refresh() and the
        // pre-edit values are gone by the time it returns. The audit entry is
        // about the transition, and "from" has to be captured while it is still
        // true — diffing against the row after the write would report every
        // field as unchanged.
        $before = $this->editableValuesOf($plugin);

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

        PluginUpdated::dispatch(
            plugin: $updated,
            user: $user,
            requestContext: $requestContext,
            changes: $this->resolveChanges($before, $this->editableValuesOf($updated)),
        );

        return $updated;
    }

    /**
     * The editable fields of a plugin, as plain scalars.
     *
     * @return array<string, mixed>
     */
    private function editableValuesOf(Plugin $plugin): array
    {
        return [
            'name' => $plugin->name,
            'title' => $plugin->title,
            'license' => $plugin->license,
            'source_link' => $plugin->source_link,
        ];
    }

    /**
     * Which fields actually changed value, and from what to what.
     *
     * Only fields whose value really moved. Re-sending a field with the value
     * it already holds is not an edit worth an audit line, and logging one would
     * claim the plugin changed when it did not.
     *
     * @param  array<string, mixed>  $before  Editable values before the write.
     * @param  array<string, mixed>  $after  Editable values after the write.
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function resolveChanges(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $to) {
            $from = $before[$field] ?? null;

            if ($from === $to) {
                continue;
            }

            $changes[$field] = ['from' => $from, 'to' => $to];
        }

        return $changes;
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

    public function getPlugin(string $id): Plugin
    {
        return $this->pluginRepository->findApprovedById($id);
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

            TrendingTracker::trackView($plugin->id);

            return new PluginViewResult(true, $plugin->view_count + $bufferedViews);
        }

        $bufferedViews = (int) Redis::hget($bufferKey, $plugin->id);

        return new PluginViewResult(false, $plugin->view_count + $bufferedViews);
    }

    /**
     * Get trending plugins using the Sliding Time-Window Algorithm.
     *
     * Formula: Score = (Views * ViewWeight) + (Comments * CommentWeight) + (Stars * StarWeight)
     *
     * The algorithm calculates the score based on recent interactions (views, comments, stars)
     * within a sliding time window (e.g., last 7 days). Older plugins can still trend if they
     * receive a surge in recent activity, as the creation date is not used as a penalty.
     *
     * @param int $perPage
     * @param User|null $user
     * @return LengthAwarePaginator
     */
    public function getTrendingPlugins(int $perPage, ?User $user = null): LengthAwarePaginator
    {
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        $page = Paginator::resolveCurrentPage() ?: 1;

        $start = ($page - 1) * $perPage;
        $end = $start + $perPage - 1;

        $total = Redis::zcard($zsetKey);

        if ($total === 0) {
            Artisan::call('plugins:refresh-trending');
            $total = Redis::zcard($zsetKey);
        }

        $ids = Redis::zrevrange($zsetKey, $start, $end);

        if (empty($ids)) {
            return new LengthAwarePaginator([], $total, $perPage, $page);
        }

        $serializedPlugins = Redis::hmget($hashKey, $ids);

        $plugins = collect($serializedPlugins)
            ->filter()
            ->map(fn ($serialized) => unserialize($serialized))
            ->values();

        if ($user !== null) {
            $starredIds = $this->starRepository->starredPluginIds(
                $plugins->pluck('id')->toArray(),
                (string) $user->getAuthIdentifier()
            );

            $plugins->each(function ($plugin) use ($starredIds) {
                $plugin->is_star = in_array($plugin->id, $starredIds, true);
            });
        }

        return new LengthAwarePaginator($plugins, $total, $perPage, $page);
    }
}
