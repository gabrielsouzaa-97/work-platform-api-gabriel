<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

use App\Models\Job;

final readonly class CancelJobCommand
{
    public function __construct(
        public Job $job,
        public ?string $actorId = null,
    ) {}
}
