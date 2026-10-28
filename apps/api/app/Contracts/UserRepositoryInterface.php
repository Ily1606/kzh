<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface UserRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    public function findByEmail(string $email): ?User;

    /**
     * The user carrying this username, or null when the username is unknown.
     */
    public function findByUsername(string $username): ?User;

    /**
     * The ID of the user carrying this username.
     *
     * @throws ModelNotFoundException when no user carries that username.
     */
    public function findIdByUsername(string $username): string;

    /**
     * Return the user matching the given credentials, or null when the email
     * is unknown, the account is locked, or the password does not match.
     */
    public function findValidCredentials(string $email, string $password): ?User;

    /**
     * Update user's basic profile information.
     *
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $profileData
     */
    public function updateProfile(User $user, array $userData, array $profileData): User;

    /**
     * Update user's avatar.
     */
    public function updateAvatar(User $user, ?string $avatarPath): User;

    /**
     * Update user's password.
     */
    public function updatePassword(User $user, string $newPassword): User;

    /**
     * Revoke all tokens for the user, except the one with the given ID.
     */
    public function revokeTokensExcept(User $user, int|string|null $exceptId = null): void;

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

    /**
     * Delete the user account (soft delete), triggering cascade deletion
     * of their plugins, comments, stars, and revoking tokens.
     */
    public function delete(User $user): bool;
}
