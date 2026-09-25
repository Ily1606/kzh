<?php

namespace App\Events\Auth;

use App\Contracts\RecordsAuthActivity;
use App\Enums\AuthEventType;
use App\Events\Concerns\InteractsWithRequestContext;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched right after the user's access tokens have been revoked.
 */
final class UserLoggedOut implements RecordsAuthActivity, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithRequestContext;

    public function __construct(
        public readonly User $user,
        public readonly int $revokedTokensCount = 0,
    ) {}

    public function user(): User
    {
        return $this->user;
    }

    public function eventType(): AuthEventType
    {
        return AuthEventType::LoggedOut;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->baseContext() + [
            'revoked_tokens' => $this->revokedTokensCount,
        ];
    }
}
