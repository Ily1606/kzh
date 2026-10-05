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
    | Comment Threading Depth
    |--------------------------------------------------------------------------
    |
    | Maximum number of levels a comment thread may have, the way Facebook
    | does it: a top-level comment, a reply and a sub-reply. A comment created
    | under a parent that already sits at this level is rejected with a 422,
    | so the tree can never grow a fourth level.
    |
    | The read endpoints are unaffected: `replies()` still walks whatever tree
    | exists, so threads that predate this limit stay fully expandable.
    |
    */
    'max_depth' => (int) env('COMMENT_MAX_DEPTH', 3),

    /*
    |--------------------------------------------------------------------------
    | Comment Content
    |--------------------------------------------------------------------------
    |
    | Maximum length of the `content` field, in characters.
    |
    */
    'content' => [
        'max_length' => (int) env('COMMENT_CONTENT_MAX_LENGTH', 2000),
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
