<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\CustomerLifecycleController;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\V1\CreateUserRequest;
use App\Http\Requests\V1\SetUserQuotaRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Services\OccPassthroughService;
use App\Modules\Customers\Support\OccQuotaValue;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TenantUserController extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(
        private readonly CustomerLifecycleController $lifecycle,
        private readonly OccPassthroughService $occ,
    ) {}

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

    public function setQuota(string $slug, string $username, SetUserQuotaRequest $request): JsonResponse
    {
        $customer = Customer::query()->where('slug', $slug)->first();

        if ($customer === null) {
            return RenderDomainError::response(DomainError::TenantNotFound);
        }

        try {
            $result = $this->occ->exec($customer, 'user:setting', [
                $username,
                'files',
                'quota',
                OccQuotaValue::forSshArgv($request->string('quota')->toString()),
            ]);
        } catch (ClusterUnreachableException) {
            return RenderDomainError::response(DomainError::ClusterUnreachable, retryAfter: 60);
        } catch (CapabilityBlockedException) {
            return RenderDomainError::response(DomainError::CapabilityNotAvailable);
        } catch (UpstreamUnavailableException) {
            return RenderDomainError::response(DomainError::UpstreamUnavailable);
        }

        $this->writeQuotaAuditLog($customer, $username, $request);

        return $this->v1SyncEnvelope($result);
    }

    private function writeQuotaAuditLog(Customer $customer, string $username, SetUserQuotaRequest $request): void
    {
        /** @var Operator $actor */
        $actor = $request->user();

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actor->id,
            'action' => 'v1_set_user_quota',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => ['username' => $username],
            'cluster_server_id' => $customer->clusterServer?->id,
        ]);
    }
}
