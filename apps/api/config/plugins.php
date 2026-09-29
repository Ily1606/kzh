<?php

use App\Enums\PluginLicense;

return [
    'licenses' => [
        PluginLicense::MIT->value,
        PluginLicense::Apache20->value,
        PluginLicense::Gpl20->value,
        PluginLicense::Gpl30->value,
        PluginLicense::Bsd3Clause->value,
        PluginLicense::Proprietary->value,
    ],
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
        'cache_ttl' => env('PLUGIN_TRENDING_CACHE_TTL', 300),

        // Only consider plugins approved within the last X days
        'days_limit' => env('PLUGIN_TRENDING_DAYS_LIMIT', 30),

        // Interaction weights for calculating trending scores
        'weights' => [
            'view' => env('PLUGIN_TRENDING_WEIGHT_VIEW', 1),
            'comment' => env('PLUGIN_TRENDING_WEIGHT_COMMENT', 5),
            'star' => env('PLUGIN_TRENDING_WEIGHT_STAR', 10),
        ],

        // Time decay parameters (Hacker News algorithm)
        'gravity' => env('PLUGIN_TRENDING_GRAVITY', 1.8),
        'age_offset' => env('PLUGIN_TRENDING_AGE_OFFSET', 2),
    ],
    // Maximum plugin submissions per minute, counted per authenticated user.
    // Read from env so each environment can tune it without a code change and redeploy.
    'submit_per_minute' => (int) env('PLUGIN_SUBMIT_PER_MINUTE', 5),
];
