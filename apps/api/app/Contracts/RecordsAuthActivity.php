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
     * Extra context attached to the event, safe to store in logs.
     *
     * @return array<string, mixed>
     */
    public function context(): array;
}
