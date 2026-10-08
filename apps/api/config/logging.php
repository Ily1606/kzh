<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'auth_channel' => env('LOG_AUTH_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Auth audit failure channel
    |--------------------------------------------------------------------------
    |
    | Channel the auth listener reports a terminal failure on. It has to differ
    | from the channel above, otherwise the report dies with the sink it is
    | trying to report on. stderr does not touch the log file, which is what
    | makes it a safe rescue when the disk is full or the sink is unreachable.
    |
    */

    'auth_failure_channel' => env('LOG_AUTH_FAILURE_CHANNEL', 'stderr'),

    /*
    |--------------------------------------------------------------------------
    | Plugin event log channel
    |--------------------------------------------------------------------------
    |
    | Channel used by plugin lifecycle listeners.
    |
    */

    'plugin_channel' => env('LOG_PLUGIN_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Plugin event failure channel
    |--------------------------------------------------------------------------
    |
    | Same reasoning as auth_failure_channel: a different channel from the one
    | the plugin audit entries went to.
    |
    */

    'plugin_failure_channel' => env('LOG_PLUGIN_FAILURE_CHANNEL', 'stderr'),

    /*
    |--------------------------------------------------------------------------
    | Comment event log channel
    |--------------------------------------------------------------------------
    |
    | Channel used by comment lifecycle listeners.
    |
    */

    'comment_channel' => env('LOG_COMMENT_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Comment event failure channel
    |--------------------------------------------------------------------------
    |
    | Same reasoning as auth_failure_channel: a different channel from the one
    | the comment audit entries went to.
    |
    */

    'comment_failure_channel' => env('LOG_COMMENT_FAILURE_CHANNEL', 'stderr'),

    /*
    |--------------------------------------------------------------------------
    | Star event log channel
    |--------------------------------------------------------------------------
    |
    | Channel used by the star lifecycle listener.
    |
    */

    'star_channel' => env('LOG_STAR_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Star event failure channel
    |--------------------------------------------------------------------------
    |
    | Same reasoning as comment_failure_channel: a different channel from the one
    | the star audit entries went to.
    |
    */

    'star_failure_channel' => env('LOG_STAR_FAILURE_CHANNEL', 'stderr'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "monthly", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'monthly' => [
            'driver' => 'monthly',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => 3,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
