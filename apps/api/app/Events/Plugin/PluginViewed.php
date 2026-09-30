<?php

namespace App\Events\Plugin;

use App\Models\Plugin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Dispatched when a user or guest successfully views a plugin (not cached/ignored).
 */
final class PluginViewed
{
    use Dispatchable;

    public readonly ?string $ipAddress;

    public readonly ?string $userAgent;

    public function __construct(
        public readonly Plugin $plugin,
        public readonly string $viewerId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->ipAddress = $ipAddress ?? app(Request::class)->ip();
        $this->userAgent = $userAgent ?? app(Request::class)->userAgent();
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'plugin_id' => $this->plugin->getKey(),
            'viewer_id' => $this->viewerId,
            'name' => $this->plugin->name,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
