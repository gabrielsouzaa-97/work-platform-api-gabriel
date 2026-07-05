<?php

declare(strict_types=1);

namespace App\Modules\Customers\Dto;

final class TenantUserSyncReport
{
    public int $inserted = 0;

    public int $updated = 0;

    public int $deleted = 0;

    public int $driftDetected = 0;
}
