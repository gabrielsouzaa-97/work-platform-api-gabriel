<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class OccPassthroughResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}
}
