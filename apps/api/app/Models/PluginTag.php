<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tag_id',
])]
class PluginTag extends Model
{
    protected $table = 'plugin_tag';

    /** The pivot table has no created_at / updated_at. */
    public $timestamps = false;

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
