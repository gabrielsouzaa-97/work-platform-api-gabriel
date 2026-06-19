<?php

declare(strict_types=1);

namespace App\Modules\Dns\Actions;

use App\Models\Customer;
use App\Modules\Dns\Contracts\DnsLookupServiceInterface;
use App\Modules\Dns\Dto\DnsLookupResult;
use App\Modules\Dns\Services\DomainDnsPlanner;

final class VerifyDomainAction
{
    public function __construct(
        private readonly DnsLookupServiceInterface $dnsLookup,
        private readonly DomainDnsPlanner $planner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Customer $customer): array
    {
        $domain = (string) $customer->domain;
        $lookup = $this->dnsLookup->lookup($domain);

        if (! $lookup->zoneManaged) {
            return [
                'status' => 'pending_manual',
                'records' => $this->planner->manualRecords(
                    $domain,
                    (string) ($customer->clusterServer?->ssh_host ?? ''),
                ),
            ];
        }

        $checks = $this->buildChecks($lookup, $domain);
        $verified = ! in_array(false, $checks, true);

        return [
            'status' => $verified ? 'verified' : 'failed',
            'checks' => $checks,
        ];
    }

    /**
     * @return array{mx: bool, spf: bool, dkim: bool, dmarc: bool}
     */
    private function buildChecks(DnsLookupResult $lookup, string $domain): array
    {
        return [
            'mx' => in_array($this->planner->mxContent($domain), $lookup->mx, true),
            'spf' => in_array($this->planner->spfContent($domain), $lookup->spf, true),
            'dkim' => $this->dkimMatches($lookup->dkim),
            'dmarc' => in_array($this->planner->dmarcContent(), $lookup->dmarc, true),
        ];
    }

    /**
     * @param  array<int, string>  $records
     */
    private function dkimMatches(array $records): bool
    {
        foreach ($records as $record) {
            if (str_starts_with($record, 'v=DKIM1')) {
                return true;
            }
        }

        return false;
    }
}
