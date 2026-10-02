<?php

namespace App\Services;

use App\Contracts\PluginRepositoryInterface;
use App\Contracts\StarRepositoryInterface;
use App\Events\Star\PluginStarred;
use App\Events\Star\PluginUnstarred;
use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
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
     * @param  RequestContext  $requestContext  Metadata of the originating request, captured at the HTTP boundary so the queued audit listener still sees the real client.
     * @return array{starred: bool, star_count: int}
     */
    public function setStarred(User $user, string $pluginId, bool $starred, RequestContext $requestContext): array
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
        $changed = DB::transaction(function () use ($plugin, $user, $starred): bool {
            if ($starred) {
                $inserted = $this->starRepository->insertIgnore($plugin->id, $user->getAuthIdentifier());

                // Only on a real insert. This one condition is the whole
                // idempotency mechanism: without it the counter would follow the
                // number of requests instead of the number of rows.
                if ($inserted) {
                    $this->pluginRepository->changeStarCount($plugin->id, 1);
                }

                return $inserted;
            }

            $removed = $this->starRepository->deleteBy($plugin->id, $user->getAuthIdentifier());

            // Mirrors the branch above. Unstarring something that was never
            // starred is a no-op, not an error, so nothing is decremented.
            if ($removed) {
                $this->pluginRepository->changeStarCount($plugin->id, -1);
            }

            return $removed;
        });

        // Announced only on a real change, for the same reason the counter moves
        // only on a real change: a repeated `starred: true` must not produce a
        // second audit entry, or the log would claim more stars than the table
        // holds. Both events implement ShouldDispatchAfterCommit, so this fires
        // once the transaction above has actually landed — an event announcing a
        // rolled-back star would be worse than no event at all.
        if ($changed) {
            $this->dispatchStarEvent($starred, $plugin->id, $user, $requestContext);
        }

        return [
            'starred' => $starred,

            // Re-read after the commit so the number matches what was persisted;
            // a concurrent unstar landing between the write and the read would
            // otherwise report a count that was already stale.
            'star_count' => (int) $plugin->refresh()->star_count,
        ];
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
