<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProvisionCustomerRequest;
use App\Http\Requests\RemoveCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Actions\RemoveCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\ConfirmationMismatchException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\RemoveInProgressException;
use App\Modules\Customers\Exceptions\StateConflictException;
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
            return response()->json([
                'error' => 'idempotency_conflict',
                'existing_job_id' => $e->getExistingJobId(),
            ], 409);
        } catch (StateConflictException $e) {
            return response()->json([
                'error' => 'state_conflict',
                'diff' => $e->getDiff(),
            ], 409);
        } catch (ClusterUnreachableException) {
            return response()->json(['error' => 'cluster_unreachable'], 503)
                ->header('Retry-After', '60');
        } catch (SshRemoteException $e) {
            return response()->json([
                'error' => 'upstream_error',
                'exit_code' => $e->remoteExitCode,
                'detail' => $e->parsedJson,
            ], 502);
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
            return response()->json(['error' => 'confirm_slug_mismatch'], 422);
        } catch (RemoveInProgressException) {
            return response()->json(['error' => 'already_in_progress'], 409);
        } catch (StateConflictException) {
            return response()->json(['error' => 'state_conflict'], 409);
        } catch (SshRemoteException $e) {
            return response()->json(['error' => 'upstream_error', 'message' => $e->getMessage()], 502);
        }

        return response()->json(['job_id' => $job->job_id], 202);
    }
}
