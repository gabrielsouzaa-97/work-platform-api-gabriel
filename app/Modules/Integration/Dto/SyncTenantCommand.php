<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

use App\Models\ClusterServer;

final readonly class SyncTenantCommand
{
    public function __construct(public ClusterServer $cluster) {}
}
