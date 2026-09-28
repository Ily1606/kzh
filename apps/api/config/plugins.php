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
    'submit_per_minute' => 5,
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
];
