<?php

namespace App\Events\Plugin;

use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a plugin edit has been persisted successfully.
 */
final class PluginUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * What the edit changed, frozen at dispatch time.
     *
     * Read once at construction time, for the same reason PluginSubmitted
     * snapshots the submission: the queued worker re-queries the model, so a
     * later attempt (10s/60s retries) would otherwise describe the row as it
     * is by then — and a second edit landing in between would be reported as
     * part of this one.
     *
     * An entry that says a plugin was renamed without saying what it was
     * renamed from cannot answer the only question an auditor asks of it, so
     * both sides of each field are kept. The values are scalars captured here
     * rather than read back off the model, which also means nothing sensitive
     * enters the payload.
     *
     * @var array<string, array{from: mixed, to: mixed}>
     */
    public readonly array $changes;

    /**
     * Identity of the plugin and its editor, frozen at dispatch time.
     *
     * @var array{plugin_id: mixed, user_id: mixed, name: string|null}
     */
    public readonly array $snapshot;

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes  The fields the edit actually altered.
     */
    public function __construct(
        public readonly Plugin $plugin,
        public readonly User $user,
        public readonly RequestContext $requestContext,
        array $changes,
    ) {
        $this->changes = $changes;

        $this->snapshot = [
            'plugin_id' => $plugin->getKey(),
            'user_id' => $user->getKey(),
            'name' => $plugin->name,
        ];
    }

    public function user(): User
    {
        return $this->user;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            ...$this->snapshot,
            'changes' => $this->changes,
            'ip_address' => $this->requestContext->ipAddress,
            'user_agent' => $this->requestContext->userAgent,
        ];
    }
}
