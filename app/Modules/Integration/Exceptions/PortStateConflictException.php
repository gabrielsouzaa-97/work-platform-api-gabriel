<?php

declare(strict_types=1);

namespace App\Modules\Integration\Exceptions;

use RuntimeException;

final class PortStateConflictException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $diff
     */
    public function __construct(public readonly array $diff = [])
    {
        parent::__construct('Upstream state conflict');
    }
}
