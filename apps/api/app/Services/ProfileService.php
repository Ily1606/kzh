<?php

namespace App\Services;

use App\Contracts\UserRepositoryInterface;
use App\Events\Profile\UserAvatarUpdated;
use App\Events\Profile\UserPasswordUpdated;
use App\Events\Profile\UserProfileUpdated;
use App\Mail\EmailChangeAlertMail;
use App\Mail\EmailChangeVerifyMail;
use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class ProfileService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Retrieve the user's profile.
     */
    public function getProfile(User $user): User
    {
        return $user->loadMissing('profile');
    }

    /**
     * Update user's basic profile information.
     */
    public function updateProfile(User $user, array $data): User
    {
        $name = $data['name'] ?? null;

        $profileData = [];
        if (array_key_exists('githubName', $data)) {
            $profileData['github_name'] = $data['githubName'];
        }
        if (array_key_exists('githubLink', $data)) {
            $profileData['github_link'] = $data['githubLink'];
        }

        $user = $this->userRepository->updateProfile($user, $name, $profileData);
        UserProfileUpdated::dispatch($user);

        return $user;
    }

    /**
     * Upload and update user's avatar.
     */
    public function updateAvatar(User $user, ?UploadedFile $file): User
    {
        $avatarPath = $file?->store('avatars', UserProfile::AVATAR_DISK);

        try {
            $user = $this->userRepository->updateAvatar($user, $avatarPath);
        } catch (Throwable $e) {
            if ($avatarPath) {
                Storage::disk(UserProfile::AVATAR_DISK)->delete($avatarPath);
            }
            throw $e;
        }
        UserAvatarUpdated::dispatch($user);

        return $user;
    }

    /**
     * Update user's password.
     *
     * @throws ValidationException
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): User
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('api.current_password_incorrect')],
            ]);
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $this->userRepository->updatePassword($user, $newPassword);

            $currentToken = $user->currentAccessToken();
            $this->userRepository->revokeTokensExcept(
                $user,
                $currentToken instanceof PersonalAccessToken ? $currentToken->getKey() : null,
            );
        });

        UserPasswordUpdated::dispatch($user);

        return $user;
    }

    public function requestEmailChange(User $user, string $currentPassword, string $newEmail): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('api.current_password_incorrect')],
            ]);
        }

        $token = Str::random(64);

        EmailChangeRequest::updateOrCreate(
            ['user_id' => $user->id],
            [
                'new_email' => $newEmail,
                'token' => $token,
                'expires_at' => now()->addMinutes(30),
            ]
        );

        Mail::to($newEmail)->send(new EmailChangeVerifyMail($token));
        Mail::to($user->email)->send(new EmailChangeAlertMail($newEmail));
    }

    public function verifyEmailChange(User $user, string $token): User
    {
        $request = EmailChangeRequest::where('user_id', $user->id)
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$request) {
            throw ValidationException::withMessages([
                'token' => [__('api.invalid_or_expired_token')],
            ]);
        }

        DB::transaction(function () use ($user, $request): void {
            $this->userRepository->updateEmail($user, $request->new_email);

            $request->delete();

            $currentToken = $user->currentAccessToken();
            $this->userRepository->revokeTokensExcept(
                $user,
                $currentToken instanceof PersonalAccessToken ? $currentToken->getKey() : null,
            );
        });

        return $user;
    }
}
