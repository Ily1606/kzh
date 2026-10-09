<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['name'];

    public function plugins(): BelongsToMany
    {
        return $this->belongsToMany(Plugin::class);
    }
}
