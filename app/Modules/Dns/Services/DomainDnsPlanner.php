<?php

declare(strict_types=1);

namespace App\Modules\Dns\Services;

final class DomainDnsPlanner
{
    public function mailHost(string $domain): string
    {
        return "mail.{$domain}";
    }

    public function cloudHost(string $domain): string
    {
        return "cloud.{$domain}";
    }

    public function webmailHost(string $domain): string
    {
        return "webmail.{$domain}";
    }

    public function mxContent(string $domain): string
    {
        return '10 '.$this->mailHost($domain);
    }

    public function spfContent(string $domain): string
    {
        return 'v=spf1 a:'.$this->mailHost($domain).' -all';
    }

    public function dmarcHost(string $domain): string
    {
        return "_dmarc.{$domain}";
    }

    public function dmarcContent(): string
    {
        return 'v=DMARC1; p=none';
    }

    public function defaultDkimHost(string $domain): string
    {
        return "default._domainkey.{$domain}";
    }

    /**
     * @return array<int, string>
     */
    public function dkimLookupHosts(string $domain): array
    {
        return [
            $this->defaultDkimHost($domain),
            "mail-api._domainkey.{$domain}",
        ];
    }

    /**
     * @return array<int, array{type: string, name: string, content: string}>
     */
    public function manualRecords(string $domain, string $targetIp): array
    {
        return [
            ['type' => 'A', 'name' => $this->cloudHost($domain), 'content' => $targetIp],
            ['type' => 'A', 'name' => $this->mailHost($domain), 'content' => $targetIp],
            ['type' => 'A', 'name' => $this->webmailHost($domain), 'content' => $targetIp],
            ['type' => 'MX', 'name' => $domain, 'content' => $this->mxContent($domain)],
            ['type' => 'TXT', 'name' => $domain, 'content' => $this->spfContent($domain)],
            ['type' => 'TXT', 'name' => $this->dmarcHost($domain), 'content' => $this->dmarcContent()],
        ];
    }
}
