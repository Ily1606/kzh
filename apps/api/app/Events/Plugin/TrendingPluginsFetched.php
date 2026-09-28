<?php

namespace App\Events\Plugin;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Dispatched when the trending plugins list is retrieved (either from cache or database).
 */
final class TrendingPluginsFetched
{
    use Dispatchable;

    public readonly ?string $ipAddress;
    public readonly ?string $userAgent;

    public function __construct(
        public readonly int $limit,
        public readonly bool $cacheHit,
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
            'limit' => $this->limit,
            'cache_hit' => $this->cacheHit,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
