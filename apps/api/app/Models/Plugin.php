<?php

namespace App\Models;

use App\Enums\PluginStatus;
use App\Observers\PluginObserver;
use Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

#[Fillable([
    'name',
    'user_id',
    'title',
    'license',
    'source_link',
])]
#[ObservedBy([PluginObserver::class])]
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
            'view_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function stars(): HasMany
    {
        return $this->hasMany(Star::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function categoryLinks(): HasMany
    {
        return $this->hasMany(CategoryPlugin::class);
    }

    public function tagLinks(): HasMany
    {
        return $this->hasMany(PluginTag::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PluginReport::class);
    }

    /**
     * The plugin's one category, or null for plugins submitted before
     * categories existed.
     *
     * @return Attribute<Category|null, $this>
     */
    protected function category(): Attribute
    {
        return Attribute::get(fn (): ?Category => $this->categories->first());
    }
}
