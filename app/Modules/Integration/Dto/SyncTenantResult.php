<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class SyncTenantResult
{
    /**
     * @param  list<array<string, mixed>>|null  $instances  null when upstream list could not be fetched
     */
    public function __construct(
        public int $inserted = 0,
        public int $updated = 0,
        public int $deleted = 0,
        public ?array $instances = null,
    ) {}
}
