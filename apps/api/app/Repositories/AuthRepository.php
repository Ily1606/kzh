<?php

namespace App\Repositories;

use App\Contracts\AuthRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

class AuthRepository implements AuthRepositoryInterface
{
    public function createUser(array $attributes): User
    {
        return User::create($attributes);
    }

    public function findValidUser(string $email, string $password): ?User
    {
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->first();

        return $user !== null && Hash::check($password, $user->password)
            ? $user
            : null;
    }

    public function createToken(User $user): NewAccessToken
    {
        return $user->createToken('api-token');
    }

    public function revokeTokens(User $user): void
    {
        $user->tokens()->delete();
    }
}
