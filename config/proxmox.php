<?php

declare(strict_types=1);

return [
    'url' => env('PROXMOX_API_URL', ''),
    'token_id' => env('PROXMOX_TOKEN_ID', ''),
    'token_secret' => env('PROXMOX_TOKEN_SECRET', ''),
    'cluster' => env('PROXMOX_CLUSTER', 'IDC-EVEO'),
];
