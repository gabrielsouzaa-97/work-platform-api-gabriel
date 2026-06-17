<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\CustomerLifecycleController;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\EnableAppsRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

final class TenantAppsController extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly CustomerLifecycleController $lifecycle) {}

    public function enableApps(Customer $customer, EnableAppsRequest $request): JsonResponse
    {
        return $this->wrapV1JsonResponse(
            $this->lifecycle->enableApps($customer, $request),
        );
    }
}
