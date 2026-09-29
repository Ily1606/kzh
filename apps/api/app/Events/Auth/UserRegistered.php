<?php

namespace App\Events\Auth;

use App\Contracts\RecordsAuthActivity;
use App\Enums\AuthEventType;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched right after a brand new account has been persisted
 * and its API token has been issued.
 *
 * The request metadata is supplied by the controller, which is the only layer
 * allowed to read the HTTP request. It must still be captured at construction
 * time rather than read from the container later: the audit listener is queued,
 * so context() runs in a worker where no originating request is available.
 */
final class UserRegistered implements RecordsAuthActivity, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
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
        return [
            'user_id' => $this->user->getKey(),
            'email' => $this->user->email,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
