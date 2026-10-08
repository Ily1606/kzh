<?php

return [
    'welcome' => [
        'subject' => 'Welcome Mail',
        'greeting' => 'Welcome to :app, :name!',
        'body' => 'Thank you for registering an account with us. We are excited to have you on board.',
        'button' => 'Visit Your Dashboard',
        'footer' => 'If you have any questions, feel free to reply to this email.',
        'thanks' => 'Thanks,',
        'team' => ':app Team',
    ],
    'email_change_verify' => [
        'subject' => 'Verify Your New Email',
        'greeting' => 'Verify Your New Email',
        'body' => 'You recently requested to change the email address for your :app account.',
        'action' => 'Please click the button below to verify this new email address.',
        'button' => 'Verify Email',
        'footer' => 'If you did not request this change, you can safely ignore this email.',
        'thanks' => 'Thanks,',
    ],
    'email_change_alert' => [
        'subject' => 'Security Alert: Email Change Requested',
        'greeting' => 'Security Alert: Email Change Requested',
        'body' => 'We noticed a request to change the email address associated with your :app account to **:email**.',
        'action' => 'If you requested this change, no further action is required from this email address.',
        'warning' => '**If you did not request this change, your account may be compromised.**',
        'footer' => 'Please secure your account immediately.',
        'thanks' => 'Thanks,',
    ],
];
