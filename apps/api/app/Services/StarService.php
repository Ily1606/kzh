<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Contracts\StarRepositoryInterface;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
     * @return array{starred: bool, star_count: int}
     */
    public function setStarred(User $user, string $pluginId, bool $starred): array
    {
        // Outside the transaction: this only reads, and a plugin that is missing
        // or not approved throws here. Opening a transaction for a request that
        // is going to 404 anyway would be wasted work.
        $plugin = $this->pluginRepository->findApprovedById($pluginId);

        // One transaction over both tables, because `stars` and
        // `plugins.star_count` are two views of the same fact and must never
        // disagree. If the counter write failed after the row landed, the user
        // would be starred with a stale count that no later set-state call could
        // ever correct. Rolling both back leaves a clean retry.
        return DB::transaction(function () use ($plugin, $user, $starred): array {
            if ($starred) {
                $inserted = $this->starRepository->insertIgnore($plugin->id, $user->getAuthIdentifier());

                // Only on a real insert. This one condition is the whole
                // idempotency mechanism: without it the counter would follow the
                // number of requests instead of the number of rows.
                if ($inserted) {
                    $this->pluginRepository->changeStarCount($plugin->id, 1);
                }

                $finalState = true;
            } else {
                $removed = $this->starRepository->deleteBy($plugin->id, $user->getAuthIdentifier());

                // Mirrors the branch above. Unstarring something that was never
                // starred is a no-op, not an error, so nothing is decremented.
                if ($removed) {
                    $this->pluginRepository->changeStarCount($plugin->id, -1);
                }

                $finalState = false;
            }

            return [
                'starred' => $finalState,

                // Re-read inside the transaction so the number matches the write
                // that just happened; a concurrent unstar landing between the
                // write and the read would otherwise report a count that was
                // already stale.
                'star_count' => (int) $plugin->refresh()->star_count,
            ];
        });
    }

    /**
     * Whether this user stars this plugin.
     *
     * 404s for a missing, soft-deleted or unapproved plugin, matching the write
     * path: reading about a plugin that cannot be starred would be a leak.
     */
    public function getStarredState(User $user, string $pluginId): bool
    {
        $plugin = $this->pluginRepository->findApprovedById($pluginId);

        return $this->starRepository->isStarred($plugin->id, $user->getAuthIdentifier());
    }
}
