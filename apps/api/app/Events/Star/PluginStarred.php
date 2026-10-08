<?php

namespace App\Events\Star;

use App\Support\RequestContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user starred a plugin.
 *
 * Two events rather than one carrying a boolean: the type is the fact. A reader
 * grepping the audit log for stars does not have to filter out unstars, and a
 * listener cannot forget to branch on the flag.
 */
final class PluginStarred implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * Identity of the star, frozen at dispatch time.
     *
     * Scalars, so the queue payload holds no row content at all. No model is
     * carried either: SerializesModels would re-query it in the worker and throw
     * ModelNotFoundException if the row is gone by then (deleting a plugin
     * cascades the stars away). That happens during unserialize, so failed()
     * never runs and the audit entry is lost with no error line.
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
