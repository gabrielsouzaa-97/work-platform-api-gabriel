<?php

declare(strict_types=1);

namespace App\Modules\Integration\Exceptions;

use RuntimeException;

final class UpstreamUnavailableException extends RuntimeException
{
    public function __construct(
        string $message = 'Upstream is unavailable.',
        int $code = 0,
        public readonly ?\Throwable $cause = null,
    ) {
        parent::__construct($message, $code, $cause);
    }

    public function transportCause(): ?\Throwable
    {
        return $this->cause ?? $this->getPrevious();
    }
}
