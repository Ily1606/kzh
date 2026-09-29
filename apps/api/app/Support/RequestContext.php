<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Request metadata captured at the HTTP boundary.
 *
 * Only the controller may build this from a Request. Services and events
 * receive plain scalars so they stay usable from console/queue callers and
 * so the values can be snapshotted at dispatch time for queued listeners.
 */
final class RequestContext
{
    public function __construct(
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self($request->ip(), $request->userAgent());
    }
}
