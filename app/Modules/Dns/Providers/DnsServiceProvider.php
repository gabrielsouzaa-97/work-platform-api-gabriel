<?php

declare(strict_types=1);

namespace App\Modules\Dns\Providers;

use App\Modules\Dns\Contracts\DnsLookupServiceInterface;
use App\Modules\Dns\Services\DnsLookupService;
use Illuminate\Support\ServiceProvider;

final class DnsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsLookupServiceInterface::class, DnsLookupService::class);
    }
}
