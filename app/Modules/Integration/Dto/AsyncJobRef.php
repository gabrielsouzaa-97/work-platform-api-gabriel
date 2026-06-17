<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class AsyncJobRef
{
    public function __construct(public string $jobId) {}
}
