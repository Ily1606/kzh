<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\UserProfileObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
