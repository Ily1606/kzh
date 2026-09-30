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

    /**
     * Key of the account, as it was when the event was dispatched.
     *
     * Declared so a listener can report on the account without touching the
     * model: the audit listener runs queued, where SerializesModels re-queries
     * the user and the row may already be gone, which would throw while the
     * failure report is being written.
     */
    public function userId(): string|int|null;

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
