<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\V1\UpdateBrandingRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Integration\Dto\SetBrandingCommand;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class BrandingV1Controller extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly PlatformPortFactory $platformPortFactory) {}

    public function update(string $slug, UpdateBrandingRequest $request): JsonResponse
    {
        $customer = Customer::query()->where('slug', $slug)->first();

        if ($customer === null) {
            return RenderDomainError::response(DomainError::TenantNotFound);
        }

        $fields = $this->brandingFieldsFrom($request);

        try {
            $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;
            if ($cluster === null) {
                return RenderDomainError::response(
                    DomainError::ClusterUnreachable,
                    retryAfter: 60,
                );
            }

            $result = $this->platformPortFactory
                ->for($cluster)
                ->setBranding(new SetBrandingCommand($customer, $fields));
        } catch (ClusterUnreachableException) {
            return RenderDomainError::response(
                DomainError::ClusterUnreachable,
                retryAfter: 60,
            );
        } catch (SshTimeoutException) {
            return RenderDomainError::response(DomainError::UpstreamUnavailable);
        } catch (SshRemoteException $e) {
            return RenderDomainError::mapSshRemoteException($e, $request);
        } catch (\RuntimeException) {
            return RenderDomainError::response(DomainError::UpstreamUnavailable);
        }

        $this->writeAuditLog($customer, $fields, $request);

        return $this->v1SyncEnvelope($result->payload);
    }

    /**
     * @return array<string, string>
     */
    private function brandingFieldsFrom(UpdateBrandingRequest $request): array
    {
        $fields = [];
        foreach (['name', 'color', 'url', 'slogan', 'imprintUrl', 'privacyUrl'] as $field) {
            if ($request->filled($field)) {
                $fields[$field] = $request->string($field)->toString();
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function writeAuditLog(Customer $customer, array $fields, UpdateBrandingRequest $request): void
    {
        /** @var Operator $actor */
        $actor = $request->user();

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actor->id,
            'action' => 'v1_set_branding',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => ['fields' => array_keys($fields)],
            'cluster_server_id' => $customer->clusterServer?->id,
        ]);
    }
}
