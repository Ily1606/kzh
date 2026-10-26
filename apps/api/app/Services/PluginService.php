<?php

namespace App\Services;

use App\Contracts\CategoryRepositoryInterface;
use App\Contracts\PluginRepositoryInterface;
use App\Contracts\StarRepositoryInterface;
use App\Contracts\TagRepositoryInterface;
use App\Enums\PluginEventType;
use App\Enums\PluginStatus;
use App\DTOs\PluginViewResult;
use App\Events\Plugin\PluginSubmitted;
use App\Events\Plugin\PluginUpdated;
use App\Jobs\RecoverTrendingJob;
use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

final class PluginService
{
    public function __construct(
        private readonly PluginRepositoryInterface $pluginRepository,
        private readonly StarRepositoryInterface $starRepository,
        private readonly PluginEventService $pluginEventService,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TagRepositoryInterface $tagRepository,
    ) {}

    /**
     * @param  User  $user  Submitter the plugin belongs to.
     * @param  array{name: string, title: string, license: string, source_link: string, category: string, tags?: array<int, string>}  $attributes  Validated submission payload.
     * @param  RequestContext  $requestContext  Metadata of the originating request.
     */
    public function submit(User $user, array $attributes, RequestContext $requestContext): Plugin
    {
        try {
            $plugin = DB::transaction(function () use ($user, $attributes): Plugin {

                $created = $this->pluginRepository->create([
                    'name' => $attributes['name'],
                    'title' => $attributes['title'],
                    'license' => $attributes['license'],
                    'source_link' => $attributes['source_link'],
                    'user_id' => $user->getAuthIdentifier(),
                ]);

                $this->syncCategoryAndTags($created, $attributes);

                $this->pluginEventService->recordOwnerAction(
                    $created,
                    PluginEventType::Created,
                );

                return $created;
            });

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
            $updated = DB::transaction(function () use ($plugin, $attributes): Plugin {
                $saved = $this->pluginRepository->update($plugin, $attributes);

                $this->syncCategoryAndTags($saved, $attributes);

                $this->pluginEventService->recordOwnerAction(
                    $saved,
                    PluginEventType::Resubmitted,
                );

                return $saved;
            });
        } catch (UniqueConstraintViolationException) {
            // Same shape as submit(): a name already held by this user comes
            // back as a field-level validation error, not a 500. Two requests
            // renaming to the same value at once both pass validation and one
            // loses here — the same trade-off submit() already makes.
            throw ValidationException::withMessages([
                'name' => [__('api.plugin_name_already_exists')],
            ]);
        }

        // The sync rewrote the pivots after they were loaded — read them again.
        $updated->load(['categories', 'tags']);

        PluginUpdated::dispatch(
            plugin: $updated,
            user: $user,
            requestContext: $requestContext,
            changes: $this->resolveChanges($before, $this->editableValuesOf($updated)),
        );

        return $updated;
    }

    /**
     * Write whichever of the two the payload carries.
     *
     * A key that is absent is left alone, so an edit that sends just `category`
     * keeps the existing tags.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function syncCategoryAndTags(Plugin $plugin, array $attributes): void
    {
        if (array_key_exists('category', $attributes)) {
            $this->updateCategory($plugin, $attributes['category']);
        }

        if (array_key_exists('tags', $attributes)) {
            $this->updateTags($plugin, (array) $attributes['tags']);
        }
    }

    /**
     * A name with no row behind it means the seeder never ran. Reported as a
     * field error rather than creating the row, which would let any client
     * invent a category.
     */
    private function updateCategory(Plugin $plugin, string $name): void
    {
        $category = $this->categoryRepository->findByName($name);

        if ($category === null) {
            throw ValidationException::withMessages([
                'category' => [__('api.plugin_unknown_category')],
            ]);
        }

        $this->pluginRepository->replaceCategory($plugin, $category);
    }

    /**
     * An empty list clears them all — how an owner removes every tag.
     *
     * @param  array<int, string>  $names
     */
    private function updateTags(Plugin $plugin, array $names): void
    {
        // Dedupe first: plugin_tag is unique on (plugin_id, tag_id), so a repeated
        // tag would fail the constraint and turn into a 500.
        $names = array_values(array_unique(array_map('trim', $names)));

        $tags = $this->tagRepository->findManyByName($names);

        if (count($tags) !== count($names)) {
            throw ValidationException::withMessages([
                'tags' => [__('api.plugin_unknown_tags')],
            ]);
        }

        $this->pluginRepository->replaceTags($plugin, $tags);
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
            'category' => $plugin->category?->name,
            // Sorted so the same set in another order is not logged as a change.
            'tags' => $plugin->tags->pluck('name')->sort()->values()->all(),
        ];
    }

    /**
     * Which fields actually changed value, and from what to what.
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
     * The caller's own plugins for the Resources page.
     *
     * `is_star` is resolved for the approved plugins on the page only. A star
     * row cannot exist on a plugin that is not approved
     *
     * @param  PluginStatus|null  $status  Restrict to one status, or null for all of them.
     */
    public function getPaginatedPluginsByUser(User $user, ?PluginStatus $status, int $perPage): LengthAwarePaginator
    {
        $paginator = $this->pluginRepository->getPaginatedPluginsByUser(
            $user->getAuthIdentifier(),
            $status,
            $perPage,
        );

        $approved = array_values(array_filter(
            $paginator->items(),
            static fn (Plugin $plugin): bool => $plugin->status === PluginStatus::Approved,
        ));

        $this->markStarredFlag($approved, (string) $user->getAuthIdentifier());

        return $paginator;
    }

    /**
     * @param  int  $perPage  Page size to paginate with.
     * @param  User|null  $user  Signed-in viewer, or null for a guest. Drives whether
     *                           each plugin carries the viewer-specific `is_star` flag.
     * @param  string|null  $category  Restrict to one category, or null for all of them.
     * @param  string|null  $search  Match against plugin name and title, or null to skip the filter.
     */
    public function getPaginatedApprovedPlugins(int $perPage, ?User $user = null, ?string $category = null, ?string $search = null): LengthAwarePaginator
    {
        $paginator = $this->pluginRepository->getPaginatedApprovedPlugins($perPage, $category, $search);

        // A guest has no stars, so there is nothing to resolve and no flag to
        // set. Skipping here is also what keeps the public list query count at
        // its old level for anonymous callers.
        if ($user === null) {
            return $paginator;
        }

        $this->markStarredFlag($paginator->items(), (string) $user->getAuthIdentifier());

        return $paginator;
    }

    /**
     * @param  User|null  $user  Signed-in viewer, or null for a guest.
     */
    public function getPlugin(string $id, ?User $user = null): Plugin
    {
        $plugin = $this->resolveVisiblePlugin($id, $user);

        if ($user !== null) {
            $this->markStarredFlag([$plugin], (string) $user->getAuthIdentifier());
        }

        return $plugin;
    }

    /**
     * The plugin behind the id, or a 404 for a caller who may not open it.
     */
    private function resolveVisiblePlugin(string $id, ?User $user): Plugin
    {
        $plugin = $this->pluginRepository->findById($id);

        if ($plugin->status === PluginStatus::Approved) {
            return $plugin;
        }

        if ($user === null || $plugin->user_id !== $user->getAuthIdentifier()) {
            throw (new ModelNotFoundException)->setModel(Plugin::class, [$id]);
        }

        return $plugin;
    }

    /**
     * Increment plugin view count if the user/guest hasn't viewed it in the last 24 hours.
     *
     * - Logged-in users: identified via User ID (Sanctum guard)
     * - Guests: identified via fingerprint (IP + User-Agent)
     * - Redis stores the key with a 24h TTL to prevent view spam
     */
    public function incrementViewIfNotViewed(string $id, string|Request $viewerId): PluginViewResult
    {
        $plugin = $this->pluginRepository->findApprovedById($id);

        if ($viewerId instanceof Request) {
            $viewerId = (string) ($viewerId->user()?->getAuthIdentifier() ?? Auth::user()?->id ?? $viewerId->fingerprint());
        }

        $cacheKey = "plugin_view:{$plugin->id}:{$viewerId}";
        $bufferKey = config('plugins.views_buffer_key');

        // Retrieve TTL from configuration (default 86400 seconds / 24 hours)
        $ttl = (int) config('plugins.view_cache_ttl', 86400);
        $secondsInHour = 3600.0;
        $hours = max(1, (int) round($ttl / $secondsInHour));

        // Use Cache::add for atomic operation to prevent race conditions (VIEW-13)
        if (Cache::add($cacheKey, true, $ttl)) {
            $bufferedViews = (int) Redis::hincrby($bufferKey, $plugin->id, 1);

            TrendingTracker::trackView($plugin->id);

            return new PluginViewResult(
                counted: true,
                viewCount: $plugin->view_count + $bufferedViews,
                message: __('api.plugin_view_counted'),
            );
        }

        $bufferedViews = (int) Redis::hget($bufferKey, $plugin->id);

        return new PluginViewResult(
            counted: false,
            viewCount: $plugin->view_count + $bufferedViews,
            message: __('api.plugin_view_already_counted', ['hours' => $hours]),
        );
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
            $autoRecover = (bool) config('plugins.trending.auto_recover', true);
            $recoveredKey = config('plugins.trending.keys.recovered', 'plugins:trending_recovered');

            if ($autoRecover && ! Redis::exists($recoveredKey)) {
                if (Cache::add('plugins:trending:recovering', true, 600)) {
                    RecoverTrendingJob::dispatch();
                }
            }

            $total = Redis::zcard($zsetKey);

            if ($total === 0 && Cache::add('plugins:trending:refresh_attempted', true, 60)) {
                Artisan::call('plugins:refresh-trending', ['--no-recover' => true]);
                $total = Redis::zcard($zsetKey);
            }
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

    /**
     * Set the transient `is_star` flag on each plugin, from one query for the
     * whole page instead of one per plugin. Only the ids passed in are sent, so
     * the result set stays the size of the set.
     *
     * `is_star` is not a column: PluginResource reads it when present and omits
     * the field otherwise (see PluginResource). Anything left unflagged is
     * therefore absent from the response rather than reported as `false`.
     *
     * @param  list<Plugin>  $plugins
     */
    private function markStarredFlag(array $plugins, string $userId): void
    {
        if ($plugins === []) {
            return;
        }

        $starredIds = $this->starRepository->starredPluginIds(
            array_map(static fn (Plugin $plugin): string => $plugin->id, $plugins),
            $userId,
        );

        foreach ($plugins as $plugin) {
            $plugin->is_star = in_array($plugin->id, $starredIds, true);
        }
    }
}
