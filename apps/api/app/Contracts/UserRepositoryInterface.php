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
     * Revoke all tokens for the user, except the one with the given ID.
     */
    public function revokeTokensExcept(User $user, int|string|null $exceptId = null): void;

    /**
     * Update user's email.
     */
    public function updateEmail(User $user, string $newEmail): User;

    /**
     * Create an email change request for a user.
     */
    public function createEmailChangeRequest(User $user, string $newEmail, string $token, int $expiresInMinutes): void;

    /**
     * Apply the email change request, updating the user's email and deleting the request, and revoking tokens.
     */
    public function applyEmailChange(User $user, \App\Models\EmailChangeRequest $request): User;
}
