<?php

declare(strict_types=1);

namespace App\Modules\Infrastructure\Providers;

use App\Modules\Infrastructure\Services\ProxmoxClient;
use Illuminate\Support\ServiceProvider;

final class InfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/proxmox.php'), 'proxmox');
        $this->app->singleton(ProxmoxClient::class);
    }
}
