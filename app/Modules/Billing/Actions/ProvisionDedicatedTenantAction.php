<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Billing\Exceptions\WhmcsApiException;
use App\Modules\Billing\Services\WhmcsClient;
use App\Modules\Customers\Dto\ProvisionPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProvisionDedicatedTenantAction
{
    public function __construct(
        private readonly WhmcsClient $whmcsClient,
    ) {}

    /**
     * @return array{customer: Customer, order_id: int, service_id: int}
     *
     * @throws WhmcsApiException
     */
    public function execute(ProvisionPayload $payload, Operator $actor): array
    {
        $cluster = ClusterServer::findOrFail($payload->clusterServerId);

        if ($cluster->status !== 'active') {
            throw new \RuntimeException('Dedicated cluster server is not active');
        }

        $orderResult = $this->whmcsClient->createDedicatedOrder([
            'domain' => [$payload->domain],
            'domaintype' => ['register'],
        ]);
        $orderId = (int) ($orderResult['orderid'] ?? 0);

        $this->whmcsClient->acceptOrder($orderId);
        $serviceId = $this->resolveServiceId($orderResult);
        $this->whmcsClient->moduleCreate($serviceId);

        return DB::transaction(function () use ($payload, $cluster, $orderId, $serviceId, $actor): array {
            $customer = $this->persistCustomer($payload, $cluster);
            $this->writeAuditLog($customer, $cluster, $orderId, $serviceId, $actor);

            return [
                'customer' => $customer,
                'order_id' => $orderId,
                'service_id' => $serviceId,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $orderResult
     */
    private function resolveServiceId(array $orderResult): int
    {
        $serviceIds = $orderResult['serviceids'] ?? $orderResult['productids'] ?? null;

        if (is_array($serviceIds) && $serviceIds !== []) {
            return (int) reset($serviceIds);
        }

        if (isset($orderResult['serviceid'])) {
            return (int) $orderResult['serviceid'];
        }

        throw new \RuntimeException('WHMCS AddOrder response missing service id');
    }

    private function persistCustomer(ProvisionPayload $payload, ClusterServer $cluster): Customer
    {
        $ghost = Customer::withTrashed()
            ->where('slug', $payload->slug)
            ->whereNotNull('deleted_at')
            ->first();

        $attributes = [
            'cluster_server_id' => $cluster->id,
            'domain' => $payload->domain,
            'status' => 'provisioning',
            'tier' => 'dedicated',
            'mail_provision_payload' => $payload->mail,
            'last_sync_at' => now(),
        ];

        if ($ghost) {
            $ghost->restore();
            $ghost->update($attributes);

            return $ghost->fresh();
        }

        return Customer::create(array_merge(['slug' => $payload->slug], $attributes));
    }

    private function writeAuditLog(
        Customer $customer,
        ClusterServer $cluster,
        int $orderId,
        int $serviceId,
        Operator $actor,
    ): void {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actor->id,
            'action' => 'dedicated_provision_initiated',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'tier' => 'dedicated',
                'order_id' => $orderId,
                'service_id' => $serviceId,
                'domain' => $customer->domain,
            ],
            'cluster_server_id' => $cluster->id,
            'job_id' => null,
        ]);
    }
}
