<?php

namespace App\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    public function findByEmail(string $email): ?User;

    /**
     * Return the user matching the given credentials, or null when the email
     * is unknown, the account is locked, or the password does not match.
     */
    public function findValidCredentials(string $email, string $password): ?User;

    /**
     * Update user's basic profile information.
     */
    public function updateProfile(User $user, ?string $name, array $profileData): User;

    /**
     * Update user's avatar.
     */
    public function updateAvatar(User $user, ?string $avatarPath): User;

    /**
     * Update user's password.
     */
    public function updatePassword(User $user, string $newPassword): User;

    /**
     * Lock the account: stamp `locked_at` and revoke every API token the user
     * holds, so a lock takes effect on sessions that are already open rather
     * than only on the next sign-in attempt.
     */
    public function lock(User $user): User;

    /**
     * Unlock the account by clearing `locked_at`. Tokens stay revoked; the user
     * has to sign in again.
     */
    public function unlock(User $user): User;
}
