<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/whmcs.php'), 'whmcs');
    }
}
