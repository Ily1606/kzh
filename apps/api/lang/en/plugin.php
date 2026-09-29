<?php

return [
    'form' => [
        'sections' => [
            'information' => 'Plugin Information',
            'status_permissions' => 'Status & Permissions',
            'statistics' => 'Statistics',
        ],
        'fields' => [
            'title' => 'Title',
            'name' => 'Name',
            'license' => 'License',
            'source_link' => 'Source Link',
            'author' => 'Author',
            'status' => 'Status',
            'approved_at' => 'Approved At',
            'stars' => 'Stars',
            'views' => 'Views',
            'comments' => 'Comments',
        ],
    ],
    'table' => [
        'columns' => [
            'plugin_name' => 'Plugin Name',
            'author' => 'Author',
            'status' => 'Status',
            'stars' => 'Stars',
            'created_at' => 'Created At',
        ],
        'filters' => [
            'status' => 'Filter by status',
        ],
    ],
    'tabs' => [
        'all' => 'All',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
];
