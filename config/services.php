<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ssh' => [
        'driver' => env('SSH_DRIVER', 'phpseclib3'),
        'pool_ttl_seconds' => 300,
        'max_pool_size' => 5,
        'connect_timeout_seconds' => 30,
        'log_fetch_timeout_seconds' => env('SSH_LOG_FETCH_TIMEOUT', 15),
    ],

    'webhook' => [
        'grace_period_hours' => env('WEBHOOK_GRACE_PERIOD_HOURS', 24),
        'replay_window_minutes' => env('WEBHOOK_REPLAY_WINDOW_MIN', 60),
        'rate_limit_per_minute' => env('WEBHOOK_RATE_LIMIT', 100),
    ],

    'customer_readiness' => [
        'probe_timeout_seconds' => env('CUSTOMER_READINESS_PROBE_TIMEOUT', 30),
        'max_attempts' => env('CUSTOMER_READINESS_MAX_ATTEMPTS', 10),
        'max_wait_seconds' => env('CUSTOMER_READINESS_MAX_WAIT_SECONDS', 1200),
    ],

];
