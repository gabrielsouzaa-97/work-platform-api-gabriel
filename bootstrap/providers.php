<?php

use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Dns\Providers\DnsServiceProvider;
use App\Modules\Mail\Providers\MailServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    InfrastructureServiceProvider::class,
    MailServiceProvider::class,
    DnsServiceProvider::class,
];
