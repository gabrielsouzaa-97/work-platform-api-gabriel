<?php

declare(strict_types=1);

namespace App\Modules\Farms\Dto;

final readonly class PlacementResult
{
    public function __construct(
        public string $farmId,
        public string $clusterServerId,
        public int $availableSlots,
    ) {}
}
