<?php

namespace App\Contracts;

use App\Enums\AuthEventType;
use App\Models\User;

/**
 * Contract implemented by every auth lifecycle event.
 *
 * Guarantees that a listener (logging, auditing, notifications, analytics...)
 * can consume any auth event without knowing its concrete class.
 */
interface RecordsAuthActivity
{
    public function user(): User;

    public function eventType(): AuthEventType;

    /**
     * IP address of the originating request, if any.
     *
     * Declared on the contract so a listener can report where an event came from
     * without building the event's whole context payload.
     */
    public function ipAddress(): ?string;

    /**
     * Extra context attached to the event, safe to store in logs.
     *
     * @return array<string, mixed>
     */
    public function context(): array;
}
