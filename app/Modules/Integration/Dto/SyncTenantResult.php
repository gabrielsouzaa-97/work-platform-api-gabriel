<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class SyncTenantResult
{
    public function __construct(
        public int $inserted = 0,
        public int $updated = 0,
        public int $deleted = 0,
    ) {}
}
