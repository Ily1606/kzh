<?php

namespace App\Models;

use App\Enums\PluginStatus;
use App\Observers\PluginObserver;
use Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(PluginObserver::class)]
#[Fillable([
    'name',
    'user_id',
    'title',
    'license',
    'source_link',
])]
class Plugin extends Model
{
    /** @use HasFactory<PluginFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The attributes that should not be serialized.
     *
     * @var list<string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'status' => PluginStatus::class,
            'star_count' => 'integer',
            'comment_count' => 'integer',
            'view_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Scope a query to only include fully approved plugins.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at');
    }
}
