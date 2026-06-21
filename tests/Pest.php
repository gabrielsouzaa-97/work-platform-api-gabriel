<?php

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\ClusterServers\Actions\SyncWebhookSecretAction;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Mockery\MockInterface;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Characterization');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit/Modules/Agents');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit/Onboarding');

pest()->extend(TestCase::class)
    ->in('Unit/Mail');

pest()->extend(TestCase::class)
    ->in('Unit/Dns');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit/Farms');

// Contract tests run against REAL upstream — opt-in via RUN_UPSTREAM_CONTRACT=1.
// They MUST NOT use RefreshDatabase (the operator seeds cluster/customer rows
// before invocation; wiping them would render the suite non-functional). The
// Pest `--filter` and the env-guard inside each `it()` keep these tests
// inert in normal CI runs.
pest()->extend(TestCase::class)
    ->in('Contract');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Guards against the two regression patterns that broke ISSUE-006:
 *  - Bug A: canonical-API vocab leaking into upstream argv (e.g. 'users:create').
 *  - Bug B: caller duplicating --async/--json (SshClient::runAsync already appends them).
 *
 * Use inside `withArgs(fn ($c, $cmd, $args) => ... && noUpstreamFlagDuplication($args, $canonicalCmd))`.
 *
 * @param  array<int, mixed>  $args  argv passed to SshClient::runAsync()
 * @param  string  $canonicalCmd  Canonical cmd (e.g. 'groups:create') that MUST NOT leak into argv
 */
function noUpstreamFlagDuplication(array $args, string $canonicalCmd): bool
{
    return ! in_array('--async', $args, true)
        && ! in_array('--json', $args, true)
        && ! in_array($canonicalCmd, $args, true);
}

/**
 * Exercise Create::save with PEM on the component instance (bypasses Livewire wire for PEM).
 */
function callCreateSaveWithPem(Testable $testable, string $pem): Testable
{
    $component = $testable->instance();
    $component->testPemOverrideBase64 = base64_encode($pem);
    $component->save(
        app(WebhookSecretGenerator::class),
        app(SyncWebhookSecretAction::class),
    );

    return $testable;
}

function fakeReadinessGateR6Http(string $domain, int $status = 200): void
{
    Http::fake([
        "https://{$domain}/apps/mework360_memail/*" => Http::response('OK', $status),
    ]);
}

function readinessGateSshMockWithGatesR1ToR5(): MockInterface
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->andReturnUsing(function (
        ClusterServer $clusterArg,
        string $cmd,
        array $argv,
    ): SshResponse {
        $occ = $argv[2] ?? '';

        if ($occ === 'app:list') {
            return new SshResponse(
                stdout: json_encode(['enabled' => ['mework360_memail' => true, 'me360_theme' => true]]),
                stderr: '',
                exitCode: 0,
                parsedJson: ['enabled' => ['mework360_memail' => true, 'me360_theme' => true]],
            );
        }

        if ($occ === 'user:list') {
            return new SshResponse(stdout: '[]', stderr: '', exitCode: 0, parsedJson: []);
        }

        if ($occ === 'config:app:get' && ($argv[4] ?? '') === 'externalLocation') {
            return new SshResponse(
                stdout: 'https://cloud.example/roundcube',
                stderr: '',
                exitCode: 0,
                parsedJson: ['value' => 'https://cloud.example/roundcube'],
            );
        }

        if ($occ === 'config:app:get' && ($argv[4] ?? '') === 'forceSSO') {
            return new SshResponse(stdout: 'yes', stderr: '', exitCode: 0, parsedJson: ['value' => 'yes']);
        }

        return new SshResponse(stdout: '', stderr: 'unexpected occ', exitCode: 1, parsedJson: null);
    });

    return $ssh;
}

function readinessSshMockWithGatesR1ToR5(): MockInterface
{
    return readinessGateSshMockWithGatesR1ToR5();
}

function bindReadinessGateMocks(Customer $customer, int $r6Status = 200): void
{
    app()->instance(SshClientInterface::class, readinessGateSshMockWithGatesR1ToR5());
    fakeReadinessGateR6Http($customer->domain, $r6Status);
}
