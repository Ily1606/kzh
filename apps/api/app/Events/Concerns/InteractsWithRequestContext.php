<?php

namespace App\Events\Concerns;

use App\Models\User;
use App\Support\RequestContext;

/**
 * Shared request metadata carried by every auth event.
 *
 * The using event declares $user / $requestContext and captures them in its own
 * constructor, while the HTTP request that triggered the event is still bound.
 * The trait only reads that stored snapshot; it must never resolve
 * app(Request::class) itself. Auth listeners are queued (see LogAuthActivity),
 * so context() runs in a queue worker where the bound request is the console
 * request and would report 127.0.0.1 / "Symfony" instead of the client that
 * performed the action.
 *
 * @property-read User $user
 * @property-read RequestContext $requestContext
 */
trait InteractsWithRequestContext
{
    /**
     * IP address of the originating request, null for non-HTTP callers.
     *
     * Exposed on its own so a failure report can include the origin without
     * building the whole event context first.
     */
    public function ipAddress(): ?string
    {
        return $this->requestContext->ipAddress;
    }

    /**
     * User agent of the originating request, null for non-HTTP callers.
     */
    public function userAgent(): ?string
    {
        return $this->requestContext->userAgent;
    }

    /**
     * Keys shared by every auth event payload.
     *
     * The concrete event merges its own attributes on top:
     *
     *     return [...$this->baseContext(), 'token_id' => $this->tokenId];
     *
     * Keeping the block here makes a new shared field (e.g. request_id) a
     * one-line change instead of an edit in every auth event.
     *
     * @return array<string, mixed>
     */
    public function baseContext(): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'email' => $this->user->email,
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
        ];
    }
}
