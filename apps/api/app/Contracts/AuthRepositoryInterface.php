<?php

namespace App\Contracts;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

interface AuthRepositoryInterface
{
    public function createUser(array $attributes): User;

    public function findValidUser(string $email, string $password): ?User;

    public function createToken(User $user): NewAccessToken;

    public function revokeTokens(User $user): void;
}
