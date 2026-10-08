<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Star extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_id',
        'user_id',
    ];
}
