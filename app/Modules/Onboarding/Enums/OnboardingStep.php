<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Enums;

enum OnboardingStep: string
{
    case ProvisionTenant = 'provision_tenant';
    case WaitReadiness = 'wait_readiness';
    case CreateAdmin = 'create_admin';
    case EnableApps = 'enable_apps';
    case SetBranding = 'set_branding';
}
