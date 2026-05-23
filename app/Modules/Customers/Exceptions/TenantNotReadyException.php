<?php

declare(strict_types=1);

namespace App\Modules\Customers\Exceptions;

final class TenantNotReadyException extends \DomainException
{
    public function __construct(
        public readonly string $customerStatus,
        public readonly int $retryAfterSeconds = 60,
    ) {
        parent::__construct('Tenant is not ready for user lifecycle operations.');
    }
}
