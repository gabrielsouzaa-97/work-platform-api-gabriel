<?php

declare(strict_types=1);

namespace App\Modules\Customers\Exceptions;

use RuntimeException;

final class RemoveInProgressException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Customer removal is already in progress or completed.');
    }
}
