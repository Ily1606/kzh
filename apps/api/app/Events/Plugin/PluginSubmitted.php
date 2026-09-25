<?php

namespace App\Events\Plugin;

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Dispatched after a plugin submission has been persisted successfully.
 */
final class PluginSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly ?string $ipAddress;

    public readonly ?string $userAgent;

    public function __construct(
        public readonly Plugin $plugin,
        public readonly User $user,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->ipAddress = $ipAddress ?? app(Request::class)->ip();
        $this->userAgent = $userAgent ?? app(Request::class)->userAgent();
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
            'plugin_id' => $this->plugin->getKey(),
            'user_id' => $this->user->getKey(),
            'name' => $this->plugin->name,
            'status' => $this->plugin->status->value,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
