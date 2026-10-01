<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Request metadata captured at the HTTP boundary.
 *
 * Only the controller may build this from a Request, via fromRequest(). Services
 * and events receive the object itself rather than reading the container, so the
 * values are already a snapshot by the time a queued listener sees them: the
 * worker has no originating request, and app(Request::class) there resolves to
 * the console request (127.0.0.1 / "Symfony").
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
