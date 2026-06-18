<?php

declare(strict_types=1);

namespace App\Modules\Integration\Exceptions;

use RuntimeException;

final class CapabilityBlockedException extends RuntimeException
{
    public function __construct(
        string $message = 'The requested capability is not available upstream.',
        public readonly int $remoteExitCode = 16,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $remoteExitCode, $previous);
    }
}
