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

    /*
    |--------------------------------------------------------------------------
    | Reserved Usernames
    |--------------------------------------------------------------------------
    | Danh sách username bị cấm đăng ký (so sánh case-insensitive).
    | Bao gồm: tên hệ thống, tên nhạy cảm, và các tên có thể gây nhầm lẫn.
    */
    'reserved_usernames' => [
        // System / admin
        'admin', 'administrator', 'root', 'system', 'api', 'www', 'mail', 'ftp',
        'support', 'help', 'info', 'contact', 'sales', 'marketing', 'security',
        'moderator', 'mod', 'staff', 'team', 'official', 'verified',
        // OAuth / auth
        'oauth', 'auth', 'login', 'logout', 'register', 'signup', 'signin',
        'password', 'reset', 'confirm', 'verify', 'activate',
        // API / technical
        'v1', 'v2', 'graphql', 'rest', 'webhook', 'callback',
        'cdn', 'static', 'assets', 'media', 'images', 'img', 'files',
        // Plugin routes conflict prevention
        'plugins', 'plugin', 'comments', 'events', 'stars', 'star',
        'users', 'user', 'author', 'authors', 'profile', 'settings',
        'categories', 'tags', 'trending', 'search', 'explore', 'discover',
        // Common sensitive
        'test', 'demo', 'example', 'sample', 'temp', 'tmp', 'dev', 'staging',
        'localhost', 'local', 'internal', 'private', 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | Username Reservation Period
    |--------------------------------------------------------------------------
    | Số ngày username cũ bị khóa sau khi user đổi username.
    | Trong khoảng này, người khác KHÔNG thể đăng ký username đó.
    | User cũ có thể lấy lại username của mình bất kỳ lúc nào.
    */
    'username_reservation_days' => (int) env('USERNAME_RESERVATION_DAYS', 30),
];