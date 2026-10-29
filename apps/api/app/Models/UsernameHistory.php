<?php

namespace App\Models;

use Database\Factories\UsernameHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'username', 'released_at', 'available_at'])]
class UsernameHistory extends Model
{
    use HasFactory;

    /** @use HasFactory<UsernameHistoryFactory> */
    protected $table = 'username_histories';

    protected $casts = [
        'released_at' => 'datetime',
        'available_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kiểm tra username này có còn trong thời gian khóa không.
     */
    public function isReserved(): bool
    {
        return $this->available_at->isFuture();
    }

    /**
     * Scope: Lấy các record còn trong thời gian khóa (chưa available).
     */
    public function scopeStillReserved($query)
    {
        return $query->where('available_at', '>', now());
    }

    /**
     * Scope: Tìm record theo username (case-insensitive).
     */
    public function scopeByUsername($query, string $username)
    {
        return $query->whereRaw('LOWER(username) = ?', [strtolower($username)]);
    }
}