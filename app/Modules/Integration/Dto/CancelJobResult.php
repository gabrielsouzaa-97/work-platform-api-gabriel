<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class CancelJobResult
{
    public function __construct(public bool $cancelled = true) {}
}
