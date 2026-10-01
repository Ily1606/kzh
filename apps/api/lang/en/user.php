<?php

return [
    'resource' => [
        'navigation_label' => 'Users',
        'model_label' => 'User',
        'plural_model_label' => 'Users',
    ],
    'table' => [
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'created_at' => 'Joined At',
            'status' => 'Status',
            'status_options' => [
                'active' => 'Active',
                'locked' => 'Blocked',
                'deleted' => 'Deleted',
            ],
        ],
        'filters' => [
            'verified' => 'Verified Email',
        ],
    ],
];
