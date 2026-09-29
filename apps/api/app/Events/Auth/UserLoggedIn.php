<?php

namespace App\Events\Auth;

use App\Contracts\RecordsAuthActivity;
use App\Enums\AuthEventType;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched right after credentials have been verified
 * and a fresh API token has been issued.
 */
final class UserLoggedIn implements RecordsAuthActivity, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $tokenId,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
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
        return [
            'user_id' => $this->user->getKey(),
            'email' => $this->user->email,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            // Identifier of the issued personal access token (useful to trace sessions).
            'token_id' => $this->tokenId,
        ];
    }
}
