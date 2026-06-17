<?php

declare(strict_types=1);

return [
    'legacy' => [
        'farm-agents:read',
        'farm-agents:write',
        'queue:read',
        'queue:write',
        'customers:write',
        'occ:write',
        'lifecycle:write',
    ],
    'v1' => [
        'tenants:read',
        'tenants:write',
        'apps:write',
        'users:write',
        'jobs:read',
        'branding:write',
        'onboarding:run',
    ],
];
