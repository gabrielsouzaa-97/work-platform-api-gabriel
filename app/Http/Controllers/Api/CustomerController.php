<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\ProvisionCustomerRequest;
use App\Http\Requests\RemoveCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Actions\RemoveCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\ConfirmationMismatchException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\RemoveInProgressException;
use App\Modules\Customers\Exceptions\StateConflictException;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use Illuminate\Http\JsonResponse;

final class CustomerController extends Controller
{
    public function store(ProvisionCustomerRequest $request, ProvisionCustomerAction $action): CustomerResource|JsonResponse
    {
        $ghost = Customer::withTrashed()
            ->where('slug', $request->string('slug')->toString())
            ->whereNotNull('deleted_at')
            ->first();

        $payload = ProvisionPayload::fromRequestWithCustomer($request, $ghost);

        try {
            $result = $action->execute($payload, $request->user());
        } catch (IdempotencyConflictException $e) {
            return RenderDomainError::idempotencyConflictResponse($request, $e);
        } catch (StateConflictException $e) {
            if (RenderDomainError::isV1($request)) {
                return RenderDomainError::respond($request, DomainError::StateConflict);
            }

            return response()->json([
                'error' => 'state_conflict',
                'diff' => $e->getDiff(),
            ], 409);
        } catch (ClusterUnreachableException) {
            return RenderDomainError::clusterUnreachableResponse($request);
        } catch (UpstreamUnavailableException|CapabilityBlockedException $e) {
            if ($response = RenderDomainError::mapPortTransportException($e, $request)) {
                return $response;
            }

            throw $e;
        }

        return new CustomerResource($result['customer']);
    }

    public function destroy(string $slug, RemoveCustomerRequest $request, RemoveCustomerAction $action): JsonResponse
    {
        try {
            $job = $action->execute(
                $slug,
                $request->string('confirm_slug')->toString(),
                $request->boolean('backup_first', true),
                $request->user(),
            );
        } catch (ConfirmationMismatchException) {
            if (RenderDomainError::isV1($request)) {
                return RenderDomainError::respond(
                    $request,
                    DomainError::ValidationFailed,
                    'The confirm_slug field does not match the tenant slug.',
                );
            }

            return response()->json(['error' => 'confirm_slug_mismatch'], 422);
        } catch (RemoveInProgressException) {
            if (RenderDomainError::isV1($request)) {
                return RenderDomainError::respond($request, DomainError::StateConflict);
            }

            return response()->json(['error' => 'already_in_progress'], 409);
        } catch (StateConflictException) {
            if (RenderDomainError::isV1($request)) {
                return RenderDomainError::respond($request, DomainError::StateConflict);
            }

            return response()->json(['error' => 'state_conflict'], 409);
        } catch (ClusterUnreachableException) {
            return RenderDomainError::clusterUnreachableResponse($request);
        } catch (UpstreamUnavailableException|CapabilityBlockedException $e) {
            if ($response = RenderDomainError::mapPortTransportException($e, $request)) {
                return $response;
            }

            throw $e;
        }

        return response()->json(['job_id' => $job->job_id], 202);
    }
}
