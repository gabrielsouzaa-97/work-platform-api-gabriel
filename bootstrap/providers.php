<?php

use App\Modules\Dns\Providers\DnsServiceProvider;
use App\Modules\Mail\Providers\MailServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    MailServiceProvider::class,
    DnsServiceProvider::class,
];
