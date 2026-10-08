<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PluginDailyView extends Model
{
    protected $table = 'plugin_daily_views';
    
    protected $fillable = [
        'plugin_id',
        'date',
        'views_count',
    ];

    public function plugin()
    {
        return $this->belongsTo(Plugin::class);
    }
}
