<?php

namespace App\Providers;

use App\Models\Operator;
use App\Modules\Core\Ssh\SshClient;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Core\Ssh\SshConnectionPool;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Core\Translators\StateTranslator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SshConnectionPool::class, function () {
            return new SshConnectionPool(
                ttlSeconds: config('services.ssh.pool_ttl_seconds', 300),
                maxPoolSize: config('services.ssh.max_pool_size', 5),
                connectTimeoutSeconds: config('services.ssh.connect_timeout_seconds', 30),
            );
        });

        $this->app->bind(SshClientInterface::class, SshClient::class);

        $this->app->singleton(JobTypeTranslator::class);
        $this->app->singleton(StateTranslator::class);
    }

    public function boot(): void
    {
        Gate::define('manage-operators', fn (Operator $user) => $user->role === 'admin');
        Gate::define('manage-cluster-servers', fn (Operator $user) => $user->role === 'admin');
    }
}
