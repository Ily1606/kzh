<?php

namespace App\Events\Auth;

use App\Contracts\RecordsAuthActivity;
use App\Enums\AuthEventType;
use App\Events\Concerns\InteractsWithRequestContext;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched right after credentials have been verified
 * and a fresh API token has been issued.
 */
final class UserLoggedIn implements RecordsAuthActivity, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithRequestContext;

    public function __construct(
        public readonly User $user,
        public readonly string $tokenId,
    ) {}

    public function user(): User
    {
        return $this->user;
    }

    public function eventType(): AuthEventType
    {
        return AuthEventType::LoggedIn;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->baseContext() + [
            // Identifier of the issued personal access token (useful to trace sessions).
            'token_id' => $this->tokenId,
        ];
    }
}
