<?php

declare(strict_types=1);

namespace App\Modules\Customers\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public function __construct(private readonly ?string $existingJobId = null)
    {
        parent::__construct('Idempotency conflict: operation already exists.');
    }

    public function getExistingJobId(): ?string
    {
        return $this->existingJobId;
    }
}
