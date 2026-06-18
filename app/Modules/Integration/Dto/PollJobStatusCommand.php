<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

use App\Models\ClusterServer;
use App\Models\Job;

final readonly class PollJobStatusCommand
{
    public function __construct(
        public Job $job,
        public ClusterServer $cluster,
    ) {}
}
