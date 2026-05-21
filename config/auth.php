<?php

declare(strict_types=1);

use App\Models\Operator;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'operators'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api-key' => [
            'driver' => 'api-key',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => Operator::class,
        ],

        'operators' => [
            'driver' => 'eloquent',
            'model' => Operator::class,
        ],
    ],

    'passwords' => [
        'operators' => [
            'provider' => 'operators',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
