<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

enum CustomerLifecycleAction: string
{
    case LifecycleAsync = 'lifecycle_async';
    case OccPanel = 'occ_panel';
    case Remove = 'remove';
    case PromoteManual = 'promote_manual';
}
