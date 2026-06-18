<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Customers\Dto\ProvisionPayload;

interface ProvisionsCustomer
{
    /**
     * @return array{customer: Customer, job: Job}
     */
    public function execute(ProvisionPayload $payload, Operator $actor): array;
}
