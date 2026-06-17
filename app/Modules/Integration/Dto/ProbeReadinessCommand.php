<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

use App\Models\Customer;

final readonly class ProbeReadinessCommand
{
    public function __construct(public Customer $customer) {}
}
