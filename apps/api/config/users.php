<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Username Generation
    |--------------------------------------------------------------------------
    |
    | The username of a new account is generated at random, so it rarely
    | collides with an existing one. A registration that loses that race is
    | retried with a new username, up to this many attempts.
    |
    */
    'username_attempts' => (int) env('USERNAME_ATTEMPTS', 3),
];
