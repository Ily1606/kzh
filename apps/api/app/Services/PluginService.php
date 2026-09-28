<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Events\Plugin\PluginSubmitted;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
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

    /**
     * Increment plugin view count if the user/guest hasn't viewed it in the last 24 hours.
     *
     * - Logged-in users: identified via User ID (Sanctum guard)
     * - Guests: identified via fingerprint (IP + User-Agent)
     * - Redis stores the key with a 24h TTL to prevent view spam
     */
    public function incrementViewIfNotViewed(string $id, Request $request): array
    {
        $plugin = Plugin::where('status', PluginStatus::Approved)
            ->whereNull('deleted_at')
            ->findOrFail($id);

        $viewerId = $request->user('sanctum')?->id ?? $request->fingerprint();

        $cacheKey = "plugin_view:{$plugin->id}:{$viewerId}";

        if (! Cache::has($cacheKey)) {
            // Buffer the view count in Redis instead of hitting DB directly (avoid locking bottleneck)
            Redis::hincrby('plugins:views_buffer', $plugin->id, 1);

            // Retrieve TTL from configuration (default 86400 seconds / 24 hours)
            $ttl = config('plugins.view_cache_ttl');
            Cache::put($cacheKey, true, $ttl);

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
}
