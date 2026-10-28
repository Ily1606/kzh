<?php

namespace App\Repositories;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * The only thing that differs from every other repository.
     */
    public function getModel(): string
    {
        return User::class;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        /** @var User $user */
        $user = parent::create($attributes);

        return $user;
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = $this->newQuery()
            ->where('email', $email)
            ->first();

        return $user;
    }

    /**
     * The user carrying this username, with the profile the public page shows.
     */
    public function findByUsername(string $username): ?User
    {
        /** @var User|null $user */
        $user = $this->newQuery()
            ->where('username', $username)
            ->with('profile')
            ->first();

        return $user;
    }

    /**
     * Credential check lives here, not in the base class: "email exists AND
     * account is not locked AND the password matches" is a business rule that
     * only this repository knows about.
     */
    public function findValidCredentials(string $email, string $password): ?User
    {
        $user = $this->newQuery()
            ->where('email', $email)
            ->whereNull('locked_at')
            ->first();

        return $user !== null && Hash::check($password, $user->password)
            ? $user
            : null;
    }

    public function updateProfile(User $user, array $userData, array $profileData): User
    {
        return DB::transaction(function () use ($user, $userData, $profileData) {
            if (! empty($userData)) {
                $user->update($userData);
            }

            if (! empty($profileData)) {
                $user->profile()->updateOrCreate([], $profileData);
            }

            return $user->refresh();
        });
    }

    public function updateAvatar(User $user, ?string $avatarPath): User
    {
        return DB::transaction(function () use ($user, $avatarPath) {
            $user->profile()->updateOrCreate([], [
                'avatar_link' => $avatarPath,
            ]);

            return $user->refresh();
        });
    }

    public function updatePassword(User $user, string $newPassword): User
    {
        $user->password = $newPassword;
        $user->save();

        return $user;
    }

    public function revokeTokensExcept(User $user, int|string|null $exceptId = null): void
    {
        $user->tokens()->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->delete();
    }

    public function lock(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $this->newQuery()->whereKey($user->getKey())->lockForUpdate()->first();
            $user->refresh();

            if ($user->locked_at === null) {
                $user->locked_at = now();
                $user->save();
            }

            return $user;
        });
    }

    public function unlock(User $user): User
    {
        $user->locked_at = null;
        $user->save();

        return $user->refresh();
    }
}
