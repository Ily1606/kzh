<?php

return [
    // All throttling lives here rather than being split between the config file
    // of the feature it protects: one place to audit, one place to tune.
    // Every value is floored at 1 when read (AppServiceProvider::resolveRateLimit),
    // so a bad env value cannot reject every request.

    // Maximum requests per minute for the auth endpoint group
    // (register, login, forgot-password, reset-password), counted per IP.
    // Read from env so each environment can tune it without a code change and redeploy.
    'auth_per_minute' => (int) env('AUTH_PER_MINUTE', 6),

    // Maximum plugin submissions per minute, counted per authenticated user.
    // Read from env so each environment can tune it without a code change and redeploy.
    'submit_plugin_per_minute' => (int) env('PLUGIN_SUBMIT_PER_MINUTE', 5),
];
