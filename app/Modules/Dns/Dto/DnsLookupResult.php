<?php

declare(strict_types=1);

namespace App\Modules\Dns\Dto;

final readonly class DnsLookupResult
{
    /**
     * @param  array<int, string>  $mx
     * @param  array<int, string>  $spf
     * @param  array<int, string>  $dkim
     * @param  array<int, string>  $dmarc
     */
    public function __construct(
        public array $mx,
        public array $spf,
        public array $dkim,
        public array $dmarc,
        public bool $zoneManaged,
    ) {}
}
