<?php

declare(strict_types=1);

namespace App\Modules\Dns\Actions;

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Dns\Services\DomainDnsPlanner;
use App\Modules\Dns\Services\PdnsClient;
use App\Modules\Farms\Dto\PlacementCriteria;
use App\Modules\Farms\Services\PlacementService;

final class ProvisionDnsZoneAction
{
    private const string META_KEY = 'dns_zone_provisioned';

    public function __construct(
        private readonly PdnsClient $pdnsClient,
        private readonly PlacementService $placementService,
        private readonly DomainDnsPlanner $planner,
    ) {}

    public function execute(Customer $customer): void
    {
        $domain = (string) $customer->domain;

        if ($domain === '' || $this->isProvisioned($customer)) {
            return;
        }

        $this->pdnsClient->ensureZoneExists($domain);
        $this->publishRecords($domain, $this->resolveFarmTargetIp($customer));
        $this->markProvisioned($customer);
    }

    private function isProvisioned(Customer $customer): bool
    {
        $meta = $customer->branding_meta ?? [];

        return ($meta[self::META_KEY] ?? false) === true;
    }

    private function markProvisioned(Customer $customer): void
    {
        $meta = $customer->branding_meta ?? [];
        $meta[self::META_KEY] = true;

        $customer->update(['branding_meta' => $meta]);
    }

    private function publishRecords(string $domain, string $targetIp): void
    {
        $this->pdnsClient->upsertRecord($domain, $this->planner->cloudHost($domain), 'A', $targetIp);
        $this->pdnsClient->upsertRecord($domain, $this->planner->mailHost($domain), 'A', $targetIp);
        $this->pdnsClient->upsertRecord($domain, $this->planner->webmailHost($domain), 'A', $targetIp);
        $this->pdnsClient->upsertRecord($domain, $domain, 'MX', $this->planner->mxContent($domain));
        $this->pdnsClient->upsertRecord($domain, $domain, 'TXT', $this->planner->spfContent($domain));
        $this->pdnsClient->upsertRecord(
            $domain,
            $this->planner->dmarcHost($domain),
            'TXT',
            $this->planner->dmarcContent(),
        );
    }

    private function resolveFarmTargetIp(Customer $customer): string
    {
        $cluster = $customer->clusterServer;
        if ($cluster !== null && $cluster->ssh_host !== '') {
            return $cluster->ssh_host;
        }

        $placement = $this->placementService->select(
            new PlacementCriteria(requiredPlatformVersion: $this->platformVersion()),
        );

        return ClusterServer::query()->findOrFail($placement->clusterServerId)->ssh_host;
    }

    private function platformVersion(): string
    {
        return (string) config('services.dns.platform_version', '1.0.0-rc.3');
    }
}
