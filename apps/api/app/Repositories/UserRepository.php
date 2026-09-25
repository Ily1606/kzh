<?php

namespace App\Repositories;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;
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

    public function updateProfile(User $user, ?string $name, array $profileData): User
    {
        if ($name !== null) {
            $user->update(['name' => $name]);
        }

        if (!empty($profileData)) {
            $user->profile()->updateOrCreate([], $profileData);
        }

        return $user->refresh();
    }

    public function updateAvatar(User $user, ?string $avatarPath): User
    {
        $user->profile()->updateOrCreate([], [
            'avatar_link' => $avatarPath
        ]);

        return $user->refresh();
    }

    public function updatePassword(User $user, string $newPassword): User
    {
        $user->password = $newPassword;
        $user->save();

        return $user;
    }
}
