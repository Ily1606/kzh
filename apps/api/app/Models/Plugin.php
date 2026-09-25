<?php

namespace App\Models;

use App\Enums\PluginStatus;
use Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'user_id',
    'title',
    'license',
    'approved_at',
    'status',
    'source_link',
    'star_count',
    'comment_count',
    'view_count',
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
}
