<?php

namespace App\Services;

use App\Contracts\AuthRepositoryInterface;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;

final class AuthService
{
    public function __construct(
        private readonly AuthRepositoryInterface $authRepository,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function register(array $attributes): array
    {
        $result = $this->issueToken($this->authRepository->createUser($attributes));

        UserRegistered::dispatch($result['user']);

        return $this->toAuthPayload($result);
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = $this->authRepository->findValidUser($email, $password);

        if ($user === null) {
            throw new InvalidCredentialsException;
        }

        $result = $this->issueToken($user);

        UserLoggedIn::dispatch($result['user'], (string) $result['token_id']);

        return $this->toAuthPayload($result);
    }

    public function logout(User $user): void
    {
        $revokedTokens = $this->authRepository->revokeTokens($user);

        UserLoggedOut::dispatch($user, $revokedTokens);
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
