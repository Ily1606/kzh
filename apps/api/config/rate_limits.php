<?php

return [
    // Maximum requests per minute for the auth endpoint group
    // (register, login, forgot-password, reset-password), counted per IP.
    // Read from env so each environment can tune it without a code change and redeploy.
    'auth_per_minute' => (int) env('AUTH_PER_MINUTE', 6),
];