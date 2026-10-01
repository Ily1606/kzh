<?php

namespace App\Events\Plugin;

use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a plugin submission has been persisted successfully.
 */
final class PluginSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * Attributes of the submission, read once at construction time.
     *
     * SerializesModels keeps the models as bare identifiers in the queue
     * payload and re-queries them in the worker, so a listener reading
     * `$this->plugin->status` would report whatever the row says at that
     * moment: an admin approval between dispatch and the first attempt (or
     * between the 10s/60s retries) would land a "Plugin submitted." entry
     * carrying `status: approved`. The audit entry has to describe the
     * submission, so the fields the log needs are frozen here and the models
     * are only kept for consumers that need the rows themselves.
     *
     * Scalars, so this stays a real snapshot and nothing sensitive is added to
     * the payload.
     *
     * @var array{plugin_id: mixed, user_id: mixed, name: string|null, status: string|null}
     */
    public readonly array $snapshot;

    public function __construct(
        public readonly Plugin $plugin,
        public readonly User $user,
        public readonly RequestContext $requestContext,
    ) {
        $this->snapshot = [
            'plugin_id' => $plugin->getKey(),
            'user_id' => $user->getKey(),
            'name' => $plugin->name,
            'status' => $plugin->status?->value,
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
            'ip_address' => $this->requestContext->ipAddress,
            'user_agent' => $this->requestContext->userAgent,
        ];
    }
}
