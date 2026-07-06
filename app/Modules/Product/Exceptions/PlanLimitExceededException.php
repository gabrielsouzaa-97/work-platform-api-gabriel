<?php

declare(strict_types=1);

namespace App\Modules\Product\Exceptions;

use RuntimeException;

final class PlanLimitExceededException extends RuntimeException
{
    public function __construct(public readonly string $limit)
    {
        parent::__construct("Plan limit exceeded: {$limit}");
    }
}
