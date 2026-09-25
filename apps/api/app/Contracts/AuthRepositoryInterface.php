<?php

namespace App\Contracts;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

interface AuthRepositoryInterface
{
    public function createUser(array $attributes): User;

    public function findValidUser(string $email, string $password): ?User;

    public function createToken(User $user): NewAccessToken;

    /**
     * Revoke every access token belonging to the user.
     *
     * @return int number of revoked tokens
     */
    public function revokeTokens(User $user): int;
}
