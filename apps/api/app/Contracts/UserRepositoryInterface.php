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
}
