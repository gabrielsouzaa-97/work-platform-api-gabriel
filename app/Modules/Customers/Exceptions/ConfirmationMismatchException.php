<?php

declare(strict_types=1);

namespace App\Modules\Customers\Exceptions;

use RuntimeException;

final class ConfirmationMismatchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Confirmation slug does not match the customer slug.');
    }
}
