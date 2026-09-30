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
    | Comment Sorting
    |--------------------------------------------------------------------------
    |
    | Supported values of the `sort` query parameter. `top` is intentionally
    | absent: ranking needs a score column, which the comments table does not
    | have yet.
    |
    */
    'sorts' => ['newest', 'oldest'],

    /*
    |--------------------------------------------------------------------------
    | Default Comment Sort
    |--------------------------------------------------------------------------
    |
    | Applied when the client omits `sort`. Must be one of the keys above.
    |
    */
    'default_sort' => 'newest',

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
