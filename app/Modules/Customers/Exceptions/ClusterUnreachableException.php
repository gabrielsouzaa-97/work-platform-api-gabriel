<?php

declare(strict_types=1);

namespace App\Modules\Customers\Exceptions;

use RuntimeException;

final class ClusterUnreachableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cluster is not active or unreachable.');
    }
}
