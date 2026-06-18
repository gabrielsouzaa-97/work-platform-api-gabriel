<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class ClusterHealthReport
{
    public function __construct(public int $exitCode) {}
}
