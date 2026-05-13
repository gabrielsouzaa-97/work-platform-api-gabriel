<?php

declare(strict_types=1);

namespace App\Modules\Customers\Exceptions;

use RuntimeException;

final class StateConflictException extends RuntimeException
{
    public function __construct(private readonly array $diff = [])
    {
        parent::__construct('State conflict: operation cannot be applied in current state.');
    }

    public function getDiff(): array
    {
        return $this->diff;
    }
}
