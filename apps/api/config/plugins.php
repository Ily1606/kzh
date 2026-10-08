<?php

use App\Enums\PluginLicense;

return [
    /*
    |--------------------------------------------------------------------------
    | Plugin View Cache TTL (Time-To-Live)
    |--------------------------------------------------------------------------
    |
    | The time-to-live (in seconds) for caching plugin views. This prevents
    | the same user or guest (by IP/User-Agent fingerprint) from artificially
    | inflating the view count multiple times within the specified timeframe.
    | Default is 86400 seconds (24 hours).
    |
    */
    'view_cache_ttl' => env('PLUGIN_VIEW_CACHE_TTL', 86400),
    'views_buffer_key' => 'plugins:views_buffer',

    // Cron schedule for the plugins:sync-views job
    'sync_views_schedule' => env('PLUGIN_SYNC_VIEWS_SCHEDULE', '*/5 * * * *'),

    /*
    |--------------------------------------------------------------------------
    | Pagination Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration values for pagination.
    |
    */
    'pagination' => [
        'default_per_page' => env('PLUGIN_DEFAULT_PER_PAGE', 15),
        'max_per_page' => env('PLUGIN_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trending API Limits Configuration
    |--------------------------------------------------------------------------
    */
    'trending_api' => [
        'default_limit' => env('PLUGIN_TRENDING_DEFAULT_LIMIT', 15),
        'max_limit' => env('PLUGIN_TRENDING_MAX_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trending Algorithm Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration values for calculating trending scores for plugins.
    |
    */
    'trending' => [
        // Cache duration for the trending list in seconds
        'cache_ttl' => (int) env('PLUGIN_TRENDING_CACHE_TTL', 300),

        // Only consider plugins approved within the last X days
        'days_limit' => (int) env('PLUGIN_TRENDING_DAYS_LIMIT', 30),

        // Master limit of items to store in the trending cache (ZSET)
        'master_limit' => (int) env('PLUGIN_TRENDING_MASTER_LIMIT', 100),

        // Cron schedule for the plugins:refresh-trending job
        'schedule' => env('PLUGIN_TRENDING_SCHEDULE', '*/15 * * * *'),

        // Interaction weights for calculating trending scores
        'weights' => [
            'view' => (float) env('PLUGIN_TRENDING_WEIGHT_VIEW', 1.0),
            'comment' => (float) env('PLUGIN_TRENDING_WEIGHT_COMMENT', 5.0),
            'star' => (float) env('PLUGIN_TRENDING_WEIGHT_STAR', 10.0),
        ],

        // Time decay parameters (Hacker News algorithm)
        'gravity' => (float) env('PLUGIN_TRENDING_GRAVITY', 1.8),
        'age_offset' => (float) env('PLUGIN_TRENDING_AGE_OFFSET', 2.0),

        // Redis Cache Keys
        'keys' => [
            'zset' => 'plugins:trending_zset',
            'objects' => 'plugins:trending_objects',
        ],
    ],
    'licenses' => array_column(PluginLicense::cases(), 'value'),
];
