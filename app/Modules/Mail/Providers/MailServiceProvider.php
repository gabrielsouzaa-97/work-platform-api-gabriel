<?php

declare(strict_types=1);

namespace App\Modules\Mail\Providers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('config', function (ConfigRepository $repository): ConfigRepository {
            return new MailAwareConfigRepository($repository);
        });
    }
}
