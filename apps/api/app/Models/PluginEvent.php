<?php

namespace App\Models;

use App\Enums\PluginEventType;
use Database\Factories\PluginEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plugin_id',
    'admin_id',
    'event_type',
    'message',
])]
class PluginEvent extends Model
{
    /** @use HasFactory<PluginEventFactory> */
    use HasFactory, HasUuids;

    /**
     * An event is append-only, so there is nothing to update. Without this,
     * Eloquent would try to write an `updated_at` the table does not have.
     */
    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => PluginEventType::class,
            'created_at' => 'datetime',
        ];
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * The reviewer who wrote this event, or null when the owner did (created,
     * resubmitted) or the admin account has since been deleted.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
