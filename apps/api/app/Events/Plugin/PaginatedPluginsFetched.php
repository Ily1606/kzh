<?php

namespace App\Events\Plugin;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Dispatched when the paginated approved plugins list is retrieved.
 */
final class PaginatedPluginsFetched
{
    use Dispatchable;

    public readonly ?string $ipAddress;
    public readonly ?string $userAgent;

    public function __construct(
        public readonly int $perPage,
        public readonly int $totalItems,
        public readonly int $currentPage,
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
            'per_page' => $this->perPage,
            'total_items' => $this->totalItems,
            'current_page' => $this->currentPage,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
