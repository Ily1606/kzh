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
    'views_buffer_key' => env('PLUGIN_VIEWS_BUFFER_KEY', 'plugins:views_buffer'),

    // Cron schedule for the plugins:sync-views job
    'sync_views_schedule' => env('PLUGIN_SYNC_VIEWS_SCHEDULE', '*/5 * * * *'),

    /*
    |--------------------------------------------------------------------------
    | Plugin Star Timeline Cache TTL
    |--------------------------------------------------------------------------
    |
    | The time-to-live (in seconds) for caching the star timeline data of a plugin.
    | Default is 900 seconds (15 minutes).
    |
    */
    'star_timeline_cache_ttl' => env('PLUGIN_STAR_TIMELINE_CACHE_TTL', 900),

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
    | Review Timeline Pagination
    |--------------------------------------------------------------------------
    */
    'events' => [
        'default_per_page' => env('PLUGIN_EVENTS_DEFAULT_PER_PAGE', 10),
        'max_per_page' => env('PLUGIN_EVENTS_MAX_PER_PAGE', 100),
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
        // Default items per page when client does not specify per_page
        'default_per_page' => (int) env('PLUGIN_TRENDING_DEFAULT_PER_PAGE', env('PLUGIN_TRENDING_PER_PAGE', 15)),

        // Maximum number of plugins allowed in the trending cache (ZSET)
        'limit' => (int) env('PLUGIN_TRENDING_LIMIT', env('PLUGIN_TRENDING_MASTER_LIMIT', 100)),

        // Only consider plugins approved within the last X days
        'days_limit' => (int) env('PLUGIN_TRENDING_DAYS_LIMIT', 30),

        // Cron schedule for the plugins:refresh-trending job
        'schedule' => env('PLUGIN_TRENDING_SCHEDULE', '*/15 * * * *'),

        // Auto recover trending statistics from DB if Redis loses interaction data
        'auto_recover' => (bool) env('PLUGIN_TRENDING_AUTO_RECOVER', true),

        // Interaction weights for calculating trending scores
        'weights' => [
            'view' => (float) env('PLUGIN_TRENDING_WEIGHT_VIEW', 1.0),
            'comment' => (float) env('PLUGIN_TRENDING_WEIGHT_COMMENT', 5.0),
            'star' => (float) env('PLUGIN_TRENDING_WEIGHT_STAR', 10.0),
        ],

        // Redis Cache Keys
        'keys' => [
            'zset' => env('PLUGIN_TRENDING_KEY_ZSET', 'plugins:trending_zset'),
            'objects' => env('PLUGIN_TRENDING_KEY_OBJECTS', 'plugins:trending_objects'),
            'active_prefix' => env('PLUGIN_TRENDING_KEY_ACTIVE_PREFIX', 'trending:active_plugins'),
            'stats_prefix' => env('PLUGIN_TRENDING_KEY_STATS_PREFIX', 'trending:stats'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cascade Deletion Configuration
    |--------------------------------------------------------------------------
    */
    'cascade_delete' => [
        'chunk_size' => (int) env('PLUGIN_CASCADE_DELETE_CHUNK_SIZE', 500),
    ],

    'licenses' => array_column(PluginLicense::cases(), 'value'),

    /*
    | How many tags one plugin may carry
    */
    'tags_per_plugin_limit' => 5,
];
