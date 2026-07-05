<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class ReadinessReport
{
    public function __construct(
        public bool $ready,
        public ?string $error = null,
        public ?string $probe = null,
    ) {}
}
