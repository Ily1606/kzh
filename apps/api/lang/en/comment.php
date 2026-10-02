<?php

return [
    'table' => [
        'columns' => [
            'plugin' => 'Plugin',
            'author' => 'Author',
            'content' => 'Comment',
            'created_at' => 'Created At',
            'plugin_status' => 'Plugin Status',
            'author_email' => 'Author Email',
            'hidden_at' => 'Hidden At',
            'updated_at' => 'Updated At',
        ],
        'values' => [
            'visible' => 'Visible',
        ],
        'filters' => [
            'plugin' => 'Filter by plugin',
            'status' => 'Filter by plugin status',
        ],
        'actions' => [
            'view' => 'View comment',
            'view_modal_heading' => 'Comment detail',
            'close' => 'Close',
        ],
        'modal' => [
            'plugin' => 'Plugin',
            'author' => 'Author',
            'published_at' => 'Published at',
            'content' => 'Content',
            'hidden_at' => 'Hidden at',
            'hidden_at_value' => 'Hidden at :date',
        ],
    ],
];
