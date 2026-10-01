<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Comment Pagination
    |--------------------------------------------------------------------------
    |
    | Comments are paginated per tree level, the way Reddit does it: the list
    | endpoint only returns the highest-level comments (parent_comment_id IS
    | NULL) and each of those exposes a `replies_count` so the client knows
    | when to call the replies endpoint. max_per_page is the hard cap applied
    | to the `per_page` query parameter.
    |
    */
    'pagination' => [
        'default_per_page' => (int) env('COMMENT_DEFAULT_PER_PAGE', 20),
        'max_per_page' => (int) env('COMMENT_MAX_PER_PAGE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Comment Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of comments a user can create per minute.
    | Minimum is always 1 (see resolveRateLimit in AppServiceProvider).
    |
    */
    'create_per_minute' => env('COMMENT_CREATE_PER_MINUTE', 10),
];
