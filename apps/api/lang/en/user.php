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
                'locked' => 'Locked',
                'deleted' => 'Deleted',
            ],
        ],
        'filters' => [
            'verified' => 'Verified Email',
        ],
        'actions' => [
            'lock' => 'Lock',
            'lock_modal_heading' => 'Lock this user?',
            'lock_modal_description' => 'The user is signed out of every device and cannot sign in again until an admin unlocks the account. This cannot be undone automatically.',
            'unlock' => 'Unlock',
            'unlock_modal_heading' => 'Unlock this user?',
            'unlock_modal_description' => 'The user can sign in again. Their previous API tokens stay revoked, so they will have to sign in on each device again.',
        ],
    ],
];
