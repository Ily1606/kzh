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
            'reject_reason_placeholder' => 'Explain what the author needs to change...',
            'reject_modal_heading' => 'Reject Plugin',
            'reject_modal_description' => 'Are you sure you want to reject this plugin? The author will see the reason below in their review history.',
            'reject_modal_submit' => 'Confirm Rejection',
            'approve' => 'Approve',
            'request_changes' => 'Request changes',
            'request_changes_message_label' => 'What needs changing?',
            'request_changes_message_placeholder' => 'Tell the author what to revise before resubmitting...',
            'request_changes_modal_heading' => 'Request Changes',
            'request_changes_modal_description' => 'The plugin stays in review and the author can revise and resubmit as often as they like. They will see your message in their review history.',
            'request_changes_modal_submit' => 'Send Request',
        ],
    ],
    'tabs' => [
        'all' => 'All',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
];
