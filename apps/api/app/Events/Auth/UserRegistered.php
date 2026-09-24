<?php

namespace App\Events\Auth;

use App\Contracts\RecordsAuthActivity;
use App\Enums\AuthEventType;
use App\Events\Concerns\InteractsWithRequestContext;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched right after a brand new account has been persisted
 * and its API token has been issued.
 */
final class UserRegistered implements RecordsAuthActivity, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithRequestContext;

    public function __construct(
        public readonly User $user,
    ) {}

    public function user(): User
    {
        return $this->user;
    }

    public function eventType(): AuthEventType
    {
        return AuthEventType::Registered;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->baseContext();
    }
}
