<?php

declare(strict_types=1);

namespace App\Modules\Farms\Exceptions;

use RuntimeException;

final class NoFarmCapacityException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No farm with available capacity matches placement criteria.');
    }
}
