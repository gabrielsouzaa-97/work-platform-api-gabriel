<?php

declare(strict_types=1);

namespace App\Modules\Farms\Dto;

final readonly class PlacementCriteria
{
    public function __construct(
        public string $requiredPlatformVersion,
    ) {}
}
