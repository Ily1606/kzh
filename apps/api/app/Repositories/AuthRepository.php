<?php

namespace App\Repositories;

use App\Contracts\AuthRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Data access for everything the auth flow needs.
 *
 * Persistence of the user itself is inherited from {@see UserRepository}: the
 * base already knows how to talk to the `users` table, so this class only
 * declares what is *different* in an auth context. Both repositories in the
 * application therefore share one skeleton (the Template Method pattern) while
 * keeping their own business rules.
 */
final class AuthRepository extends UserRepository implements AuthRepositoryInterface
{
    /**
     * Name stored on every token issued by this application.
     */
    private const TOKEN_NAME = 'api-token';

    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUser(array $attributes): User
    {
        return parent::create($attributes);
    }

    /**
     * Credential verification is a rule this repository owns: the shared
     * {@see BaseRepository::find()} must stay generic, so the locked-account
     * and password checks live in a hook of their own.
     */
    public function findValidUser(string $email, string $password): ?User
    {
        $user = $this->newQuery()
            ->where('email', $email)
            ->whereNull('locked_at')
            ->first();

        return $user !== null && Hash::check($password, $user->password)
            ? $user
            : null;
    }

    public function findByEmailWithTrashed(string $email): ?User
    {
        $normalizedEmail = Str::lower(trim($email));

        // 1. Prioritize active (non-deleted) account
        $activeUser = $this->newQuery()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if ($activeUser !== null) {
            return $activeUser;
        }

        // 2. If no active account exists, find the most recently deleted account
        return $this->newQuery()
            ->onlyTrashed()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->latest('deleted_at')
            ->first();
    }

    public function createToken(User $user): NewAccessToken
    {
        return $user->createToken(self::TOKEN_NAME);
    }

    public function revokeTokens(User $user): int
    {
        return $user->tokens()->delete();
    }
}
