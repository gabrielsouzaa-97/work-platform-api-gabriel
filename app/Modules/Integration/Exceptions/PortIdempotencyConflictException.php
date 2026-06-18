<?php

declare(strict_types=1);

namespace App\Modules\Integration\Exceptions;

use RuntimeException;

final class PortIdempotencyConflictException extends RuntimeException
{
    public function __construct(public readonly ?string $existingJobId = null)
    {
        parent::__construct('Idempotency conflict on upstream dispatch');
    }
}
