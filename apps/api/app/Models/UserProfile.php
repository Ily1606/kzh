<?php

namespace App\Models;

use App\Observers\UserProfileObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'avatar_link',
    'github_name',
    'github_link',
])]
#[ObservedBy([UserProfileObserver::class])]
class UserProfile extends Model
{
    use HasUuids;

    public const AVATAR_DISK = 'public';

    public function isLocalAvatar(?string $url = null): bool
    {
        $url = $url ?? $this->avatar_link;

        return $url && ! str_starts_with($url, 'http');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full URL for the user's avatar.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->isLocalAvatar()) {
                    return $this->avatar_link;
                }

                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk(self::AVATAR_DISK);

                return $disk->url($this->avatar_link);
            }
        );
    }
}
