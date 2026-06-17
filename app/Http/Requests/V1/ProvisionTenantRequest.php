<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Requests\ProvisionCustomerRequest;

final class ProvisionTenantRequest extends ProvisionCustomerRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
