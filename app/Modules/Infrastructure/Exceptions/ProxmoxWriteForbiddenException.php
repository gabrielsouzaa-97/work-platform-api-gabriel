<?php

declare(strict_types=1);

namespace App\Modules\Infrastructure\Exceptions;

use RuntimeException;

final class ProxmoxWriteForbiddenException extends RuntimeException
{
    public static function forMethod(string $method): self
    {
        return new self("ProxmoxClient allows GET requests only; [{$method}] is forbidden.");
    }
}
