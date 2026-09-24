<?php

namespace App\Services;

use App\Contracts\AuthRepositoryInterface;
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
        return $this->issueToken($this->authRepository->createUser($attributes));
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

        return $this->issueToken($user);
    }

    public function logout(User $user): void
    {
        $this->authRepository->revokeTokens($user);
    }

    /**
     * @return array{user: User, token: string}
     */
    private function issueToken(User $user): array
    {
        return [
            'user' => $user,
            'token' => $this->authRepository->createToken($user)->plainTextToken,
        ];
    }
}
