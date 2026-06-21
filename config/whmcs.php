<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('WHMCS_ENABLED', false),
    'url' => env('WHMCS_API_URL', ''),
    'identifier' => env('WHMCS_API_IDENTIFIER', ''),
    'secret' => env('WHMCS_API_SECRET', ''),
    'webhook_secret' => env('WHMCS_WEBHOOK_SECRET', ''),
    'product_id' => (int) env('WHMCS_PRODUCT_ID', 7),
    'dedicated_product_id' => (int) env('WHMCS_DEDICATED_PRODUCT_ID', 6),
];
