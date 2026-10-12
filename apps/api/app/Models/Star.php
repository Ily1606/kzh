<?php

namespace App\Models;

use App\Observers\StarObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([StarObserver::class])]
class Star extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $primaryKey = 'plugin_id';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * Set the keys for a save update query (handles composite primary key).
     *
     * @param  Builder  $query
     * @return Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('plugin_id', $this->getAttribute('plugin_id'))
            ->where('user_id', $this->getAttribute('user_id'));
    }
}

