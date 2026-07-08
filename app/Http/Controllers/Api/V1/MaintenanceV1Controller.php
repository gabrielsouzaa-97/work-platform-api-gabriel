<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\V1\ToggleMaintenanceRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Services\OccPassthroughService;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class MaintenanceV1Controller extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly OccPassthroughService $occ) {}

    public function toggle(string $slug, ToggleMaintenanceRequest $request): JsonResponse
    {
        $customer = Customer::query()->where('slug', $slug)->first();

        if ($customer === null) {
            return RenderDomainError::response(DomainError::TenantNotFound);
        }

        $mode = $request->boolean('on') ? '--on' : '--off';

        try {
            $result = $this->occ->exec($customer, 'maintenance:mode', [$mode]);
        } catch (ClusterUnreachableException) {
            return RenderDomainError::response(DomainError::ClusterUnreachable, retryAfter: 60);
        } catch (CapabilityBlockedException) {
            return RenderDomainError::response(DomainError::CapabilityNotAvailable);
        } catch (UpstreamUnavailableException) {
            return RenderDomainError::response(DomainError::UpstreamUnavailable);
        }

        $this->writeAuditLog($customer, $request);

        return $this->v1SyncEnvelope($result);
    }

    private function writeAuditLog(Customer $customer, ToggleMaintenanceRequest $request): void
    {
        /** @var Operator $actor */
        $actor = $request->user();

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actor->id,
            'action' => 'v1_toggle_maintenance',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => ['on' => $request->boolean('on')],
            'cluster_server_id' => $customer->clusterServer?->id,
        ]);
    }
}
