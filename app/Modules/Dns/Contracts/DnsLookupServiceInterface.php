<?php

declare(strict_types=1);

namespace App\Modules\Dns\Contracts;

use App\Modules\Dns\Dto\DnsLookupResult;

interface DnsLookupServiceInterface
{
    public function lookup(string $domain): DnsLookupResult;
}
