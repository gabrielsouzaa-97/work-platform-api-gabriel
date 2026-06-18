<?php

namespace App\Providers;

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Modules\Core\Ssh\SshClient;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Core\Ssh\SshConnectionPool;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Core\Translators\StateTranslator;
use App\Modules\Integration\Adapters\AgentPlatformAdapter;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Services\PlatformPortFactory;
use App\Observers\ClusterServerObserver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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

        $this->app->bind(SshPlatformAdapter::class);
        $this->app->bind(AgentPlatformAdapter::class);
        $this->app->bind(PlatformPortFactory::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        ClusterServer::observe(ClusterServerObserver::class);

        Auth::viaRequest('api-key', function (Request $request): ?Operator {
            $token = $request->bearerToken();
            if (! $token) {
                return null;
            }

            $hash = hash('sha256', $token);

            $apiKey = ApiKey::where('token_hash', $hash)
                ->whereNull('revoked_at')
                ->with('operator')
                ->first();

            if (! $apiKey || ! $apiKey->operator) {
                return null;
            }

            $apiKey->update(['last_used_at' => now()]);

            $request->attributes->set('api_key', $apiKey);

            return $apiKey->operator;
        });

        Gate::define('manage-operators', fn (Operator $user) => $user->status === 'active' && $user->role === 'admin');
        Gate::define('manage-cluster-servers', fn (Operator $user) => $user->status === 'active' && $user->role === 'admin');
        Gate::define('provision-customers', fn (Operator $user) => $user->status === 'active'
            && in_array($user->role, ['admin', 'operador'], true));

        // Only include routes registered in routes/api.php (middleware group 'api').
        // This prevents web routes like /api-keys (Livewire) from appearing in the docs.
        Scramble::routes(function (Route $route): bool {
            return in_array('api', $route->gatherMiddleware(), true)
                || str_starts_with($route->uri(), 'api/');
        });

        Scramble::extendOpenApi(function (OpenApi $openApi): void {
            $openApi->secure(
                SecurityScheme::http('bearer'),
            );
        });
    }
}
