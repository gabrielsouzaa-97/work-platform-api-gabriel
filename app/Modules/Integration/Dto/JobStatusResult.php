<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class JobStatusResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $state,
        public array $payload = [],
    ) {}
}
