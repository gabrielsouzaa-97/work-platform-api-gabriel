<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\CustomerLifecycleController;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateUserRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantUserController extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly CustomerLifecycleController $lifecycle) {}

    public function createUser(Customer $customer, CreateUserRequest $request): JsonResponse
    {
        return $this->wrapV1JsonResponse(
            $this->lifecycle->createUser($customer, $request),
        );
    }

    public function deleteUser(Customer $customer, string $username, Request $request): JsonResponse
    {
        return $this->wrapV1JsonResponse(
            $this->lifecycle->deleteUser($customer, $username, $request),
        );
    }
}
