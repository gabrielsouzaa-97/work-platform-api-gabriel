<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class BrandingResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}
}
