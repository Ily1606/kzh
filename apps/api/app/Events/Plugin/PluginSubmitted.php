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

    public function __construct(
        public readonly Plugin $plugin,
        public readonly User $user,
        public readonly RequestContext $requestContext,
    ) {}

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
            'plugin_id' => $this->plugin->getKey(),
            'user_id' => $this->user->getKey(),
            'name' => $this->plugin->name,
            'status' => $this->plugin->status->value,
            'ip_address' => $this->requestContext->ipAddress,
            'user_agent' => $this->requestContext->userAgent,
        ];
    }
}
