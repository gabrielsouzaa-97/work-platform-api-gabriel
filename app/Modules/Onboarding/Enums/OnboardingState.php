<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Enums;

enum OnboardingState: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Partial = 'partial';
}
