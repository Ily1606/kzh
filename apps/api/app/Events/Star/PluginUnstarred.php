<?php

namespace App\Events\Star;

use App\Support\RequestContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user removed their star from a plugin.
 *
 * Separate from PluginStarred rather than a flag on one event: the two answer
 * different questions when the log is read, and "how often do users take a star
 * back" is not the same query as "what is being starred".
 */
final class PluginUnstarred implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * Identity of the star that was removed, frozen at dispatch time.
     *
     * Scalars, for the same reason as PluginStarred: no row content in the queue
     * payload, and no model that a later unserialize could fail to re-query.
     *
     * @var array{plugin_id: string, user_id: string}
     */
    public readonly array $snapshot;

    public function __construct(
        string $pluginId,
        string $userId,
        public readonly RequestContext $requestContext,
    ) {
        $this->snapshot = [
            'plugin_id' => $pluginId,
            'user_id' => $userId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            ...$this->snapshot,
            'ip_address' => $this->requestContext->ipAddress,
            'user_agent' => $this->requestContext->userAgent,
        ];
    }
}
