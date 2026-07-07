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
        'users:read',
        'jobs:read',
        'branding:write',
        'maintenance:write',
        'onboarding:run',
        'product:read',
        'product:write',
    ],
    'scopes' => [
        'farm-agents:read' => 'Read farm agent inventory',
        'farm-agents:write' => 'Manage farm agents',
        'queue:read' => 'Read job queue',
        'queue:write' => 'Manage job queue',
        'customers:write' => 'Provision and manage customers',
        'occ:write' => 'Execute OCC passthrough commands',
        'lifecycle:write' => 'Run tenant lifecycle commands',
        'tenants:read' => 'Read tenant metadata',
        'tenants:write' => 'Provision and manage tenants',
        'apps:write' => 'Enable or disable tenant apps',
        'users:write' => 'Manage tenant users',
        'users:read' => 'Read tenant users',
        'jobs:read' => 'Read async job status',
        'branding:write' => 'Update tenant branding',
        'maintenance:write' => 'Toggle tenant maintenance mode',
        'onboarding:run' => 'Run onboarding sagas',
        'product:read' => 'Read product plans',
        'product:write' => 'Manage product plans',
    ],
];
