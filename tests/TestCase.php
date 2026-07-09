<?php

namespace Tests;

use App\Modules\Agents\Services\AgentTransportResolver;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Integration\Adapters\AgentPlatformAdapter;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Services\PlatformPortFactory;
use App\Modules\Jobs\Services\TransportObservability;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'testing',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'services.agent.transport_enabled' => false,
            'services.ssh.driver' => 'phpseclib3',
        ]);
        Http::swap(new Factory);

        foreach ([
            CustomerReadinessProbe::class,
            PlatformPortFactory::class,
            SshPlatformAdapter::class,
            AgentPlatformAdapter::class,
            SshClientInterface::class,
            AgentTransportResolver::class,
            AgentUpstreamGateway::class,
            TransportObservability::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        $this->withoutVite();
    }

    /**
     * Overrides DB config to SQLite :memory: immediately after the app is created
     * and BEFORE setUpTraits() triggers RefreshDatabase::migrate:fresh.
     *
     * Container env vars (Docker) populate $_ENV at process start and take priority
     * over phpunit.xml <env> tags via Laravel's ImmutableRepository. Overriding the
     * resolved config here is the only reliable way to prevent migrate:fresh from
     * running against the production MariaDB database.
     */
    public function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->app->make('config')->set([
            'app.env' => 'testing',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'services.agent.transport_enabled' => false,
            'services.ssh.driver' => 'phpseclib3',
        ]);

        // Ensure migrate:fresh runs on the fresh SQLite DB even if a previous
        // test run (in the same process) already set the migrated flag.
        RefreshDatabaseState::$migrated = false;
    }
}
