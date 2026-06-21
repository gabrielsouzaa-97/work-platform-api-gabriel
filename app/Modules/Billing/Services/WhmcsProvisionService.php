<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use App\Modules\Farms\Dto\PlacementCriteria;
use App\Modules\Farms\Services\PlacementService;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;

final class WhmcsProvisionService
{
    public function __construct(
        private readonly ProvisionCustomerAction $provisionAction,
        private readonly PlacementService $placementService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{customer: Customer, job_id: string}
     *
     * @throws ClusterUnreachableException
     * @throws IdempotencyConflictException
     * @throws StateConflictException
     * @throws UpstreamUnavailableException
     */
    public function provisionFromWebhook(array $payload, Operator $actor): array
    {
        $slug = (string) ($payload['tenant_slug'] ?? $payload['slug'] ?? '');
        $domain = (string) ($payload['domain'] ?? '');

        if ($slug === '' || $domain === '') {
            throw new \InvalidArgumentException('WHMCS webhook missing tenant_slug or domain');
        }

        if (Customer::query()->find($slug) !== null) {
            $customer = Customer::query()->findOrFail($slug);
            $job = $customer->jobs()->latest('queued_at')->first();

            return [
                'customer' => $customer,
                'job_id' => $job !== null ? (string) $job->job_id : '',
            ];
        }

        $clusterServerId = $this->resolveClusterServerId($payload);
        $provisionPayload = new ProvisionPayload(
            slug: $slug,
            domain: $domain,
            clusterServerId: $clusterServerId,
            apps: is_array($payload['apps'] ?? null) ? $payload['apps'] : [],
            fullApps: (bool) ($payload['full_apps'] ?? false),
            logoPath: null,
            backgroundPath: null,
            mail: is_array($payload['mail'] ?? null) ? $payload['mail'] : null,
        );

        // N22.1: provision only after InvoicePaid; welcome email stays in ProbeCustomerReadinessJob.
        $result = $this->provisionAction->execute($provisionPayload, $actor);

        return [
            'customer' => $result['customer'],
            'job_id' => (string) $result['job']->job_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveClusterServerId(array $payload): string
    {
        if (($payload['auto_place'] ?? false) === true) {
            return $this->placementService->select(
                new PlacementCriteria(requiredPlatformVersion: $this->platformVersion()),
            )->clusterServerId;
        }

        $clusterServerId = (string) ($payload['cluster_server_id'] ?? '');

        if ($clusterServerId === '') {
            throw new \InvalidArgumentException('WHMCS webhook missing cluster_server_id or auto_place');
        }

        return $clusterServerId;
    }

    private function platformVersion(): string
    {
        return (string) config('services.dns.platform_version', '1.0.0-rc.3');
    }
}
