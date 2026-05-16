<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        // Ensure migrate:fresh runs on the fresh SQLite DB even if a previous
        // test run (in the same process) already set the migrated flag.
        RefreshDatabaseState::$migrated = false;
    }
}
