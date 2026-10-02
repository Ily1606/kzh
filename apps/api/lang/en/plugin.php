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
            'name' => 'Name',
            'title' => 'Title',
            'author' => 'Author',
            'status' => 'Status',
            'stars' => 'Stars',
            'views' => 'Views',
            'comments' => 'Comments',
            'license' => 'License',
            'source_link' => 'Source Link',
            'approved_at' => 'Approved At',
            'created_at' => 'Created At',
        ],
        'filters' => [
            'status' => 'Filter by status',
        ],
        'actions' => [
            'reject' => 'Reject',
            'reject_reason_label' => 'Rejection Reason',
            'reject_reason_placeholder' => 'Enter the reason for rejection...',
            'reject_modal_heading' => 'Reject Plugin',
            'reject_modal_description' => 'Are you sure you want to reject this plugin? The author will receive the reason entered below.',
            'reject_modal_submit' => 'Confirm Rejection',
            'approve' => 'Approve'
        ],
    ],
    'tabs' => [
        'all' => 'All',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
];
