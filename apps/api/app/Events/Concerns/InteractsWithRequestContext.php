<?php

namespace App\Events\Concerns;

use Illuminate\Http\Request;

/**
 * Shared request metadata carried by every auth event.
 *
 * Metadata is captured on demand instead of stored, so the event stays
 * safe to serialize/dispatch onto a queue.
 */
trait InteractsWithRequestContext
{
    public function ipAddress(): ?string
    {
        return app(Request::class)->ip();
    }

    public function userAgent(): ?string
    {
        return app(Request::class)->userAgent();
    }

    /**
     * Attributes shared by every auth event payload.
     *
     * @return array<string, mixed>
     */
    public function baseContext(): array
    {
        return [
            'user_id' => $this->user()->getKey(),
            'email' => $this->user()->email,
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
        ];
    }
}
