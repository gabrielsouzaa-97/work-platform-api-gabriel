<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\V1\ProvisionTenantRequest;
use App\Http\Requests\V1\RemoveTenantRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\V1\TenantResource;
use App\Models\Customer;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Actions\RemoveCustomerAction;
use Illuminate\Http\JsonResponse;

final class TenantController extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly CustomerController $customerController) {}

    public function store(
        ProvisionTenantRequest $request,
        ProvisionCustomerAction $action,
    ): JsonResponse {
        $result = $this->customerController->store($request, $action);

        if ($result instanceof CustomerResource) {
            return $this->wrapV1ProvisionResource($result);
        }

        return $result;
    }

    public function show(string $slug): JsonResponse
    {
        $customer = Customer::query()->where('slug', $slug)->first();

        if ($customer === null) {
            return RenderDomainError::response(DomainError::TenantNotFound);
        }

        return $this->v1SyncEnvelope(new TenantResource($customer));
    }

    public function destroy(
        string $slug,
        RemoveTenantRequest $request,
        RemoveCustomerAction $action,
    ): JsonResponse {
        return $this->wrapV1JsonResponse(
            $this->customerController->destroy($slug, $request, $action),
        );
    }
}
