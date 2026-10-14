<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A plugin's link to a category. At most one of these exists per plugin.
 */
#[Fillable([
    'category_id',
])]
class CategoryPlugin extends Model
{
    protected $table = 'category_plugin';

    /** The pivot table has no created_at / updated_at. */
    public $timestamps = false;

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
