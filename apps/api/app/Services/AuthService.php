<?php

namespace App\Services;

use App\Contracts\AuthRepositoryInterface;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Support\RequestContext;

final class AuthService
{
    public function __construct(
        private readonly AuthRepositoryInterface $authRepository,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes  Validated registration payload.
     * @param  RequestContext  $requestContext  Metadata of the originating request.
     * @return array{user: User, token: string}
     */
    public function register(array $attributes, RequestContext $requestContext): array
    {
        $result = $this->issueToken($this->authRepository->createUser($attributes));

        UserRegistered::dispatch($result['user'], $requestContext);

        return $this->toAuthPayload($result);
    }

    /**
     * @param  string  $email  Email address the user signs in with.
     * @param  string  $password  Plain-text password to verify.
     * @param  RequestContext  $requestContext  Metadata of the originating request.
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password, RequestContext $requestContext): array
    {
        $normalizedEmail = Str::lower($email);
        $rateLimitKey = 'login_account:' . $normalizedEmail;
        $maxAttempts = (int) config('auth.limiters.login_per_account', 30);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            throw new ThrottleRequestsException('Too Many Attempts.');
        }

        $user = $this->authRepository->findValidUser($email, $password);

        if ($user === null) {
            RateLimiter::hit($rateLimitKey);
            throw new InvalidCredentialsException;
        }

        RateLimiter::clear($rateLimitKey);

        $result = $this->issueToken($user);

        UserLoggedIn::dispatch($result['user'], (string) $result['token_id'], $requestContext);

        return $this->toAuthPayload($result);
    }

    /**
     * Revoke every token of the user and dispatch the logout audit event.
     *
     * @param  User  $user  User whose tokens are revoked.
     * @param  RequestContext  $requestContext  Metadata of the originating request.
     */
    public function logout(User $user, RequestContext $requestContext): void
    {
        $revokedTokens = $this->authRepository->revokeTokens($user);

        UserLoggedOut::dispatch($user, $requestContext, $revokedTokens);
    }

    /**
     * @return array{user: User, token: string, token_id: int|string}
     */
    private function issueToken(User $user): array
    {
        $token = $this->authRepository->createToken($user);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_id' => $token->accessToken->getKey(),
        ];
    }

    /**
     * Keep the internal token id out of the HTTP response payload.
     *
     * @param  array{user: User, token: string, token_id: int|string}  $result
     * @return array{user: User, token: string}
     */
    private function toAuthPayload(array $result): array
    {
        return [
            'user' => $result['user'],
            'token' => $result['token'],
        ];
    }
}
