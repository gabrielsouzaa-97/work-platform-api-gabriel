<?php

declare(strict_types=1);

namespace App\Modules\Dns\Services;

use App\Modules\Dns\Contracts\DnsLookupServiceInterface;
use App\Modules\Dns\Dto\DnsLookupResult;

final class DnsLookupService implements DnsLookupServiceInterface
{
    public function __construct(
        private readonly DomainDnsPlanner $planner,
    ) {}

    public function lookup(string $domain): DnsLookupResult
    {
        return new DnsLookupResult(
            mx: $this->lookupMx($domain),
            spf: $this->lookupTxtMatching($domain, 'v=spf1'),
            dkim: $this->lookupDkim($domain),
            dmarc: $this->lookupTxtMatching($this->planner->dmarcHost($domain), 'v=DMARC1'),
            zoneManaged: $this->zoneIsManaged($domain),
        );
    }

    /**
     * @return array<int, string>
     */
    private function lookupMx(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_MX);

        if (! is_array($records)) {
            return [];
        }

        $mx = [];
        foreach ($records as $record) {
            if (! isset($record['pri'], $record['target'])) {
                continue;
            }

            $mx[] = $record['pri'].' '.$record['target'];
        }

        return $mx;
    }

    /**
     * @return array<int, string>
     */
    private function lookupTxtMatching(string $name, string $prefix): array
    {
        $records = @dns_get_record($name, DNS_TXT);

        if (! is_array($records)) {
            return [];
        }

        $matches = [];
        foreach ($records as $record) {
            $txt = $record['txt'] ?? null;
            if (! is_string($txt) || ! str_starts_with($txt, $prefix)) {
                continue;
            }

            $matches[] = $txt;
        }

        return $matches;
    }

    /**
     * @return array<int, string>
     */
    private function lookupDkim(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_NS);

        if (! is_array($records)) {
            return [];
        }

        $matches = [];
        foreach ($records as $record) {
            $host = $record['host'] ?? null;
            if (! is_string($host) || ! str_contains($host, '_domainkey')) {
                continue;
            }

            $matches = array_merge($matches, $this->lookupTxtMatching($host, 'v=DKIM1'));
        }

        return $matches;
    }

    private function zoneIsManaged(string $domain): bool
    {
        try {
            app(PdnsClient::class)->getZone($domain);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
