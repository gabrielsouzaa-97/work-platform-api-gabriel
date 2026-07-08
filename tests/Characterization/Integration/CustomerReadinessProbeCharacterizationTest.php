<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Integration\Adapters\AgentPlatformAdapter;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    Config::set('services.agent.transport_enabled', false);
    config([
        'cache.default' => 'array',
        'services.customer_readiness.probe_timeout_seconds' => 25,
    ]);

    resetCustomerReadinessProbeContainer();

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('run');
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);
});

function resetCustomerReadinessProbeContainer(): void
{
    app()->forgetInstance(CustomerReadinessProbe::class);
    app()->forgetInstance(PlatformPortFactory::class);
    app()->forgetInstance(SshPlatformAdapter::class);
    app()->forgetInstance(AgentPlatformAdapter::class);
    app()->forgetInstance(SshClientInterface::class);
}

function bindCustomerReadinessProbeSsh(SshClientInterface $ssh): void
{
    resetCustomerReadinessProbeContainer();
    app()->instance(SshClientInterface::class, $ssh);
}

function characterizationProbeCustomer(string $slug = 'char-probe-acme'): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'provisioning_finishing',
    ]);
}

function characterizationReadinessGateMock(): SshClientInterface
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

it('characterizes readiness gates include app:list, user:list, and memail config', function (): void {
    $customer = characterizationProbeCustomer();

    bindCustomerReadinessProbeSsh(characterizationReadinessGateMock());
    fakeReadinessGateR6Http($customer->domain);

    expect(app(CustomerReadinessProbe::class)->isReady($customer))->toBeTrue();
});

it('characterizes all gates passing returns true', function (): void {
    $customer = characterizationProbeCustomer('char-probe-ok');

    bindCustomerReadinessProbeSsh(characterizationReadinessGateMock());
    fakeReadinessGateR6Http($customer->domain);

    expect(app(CustomerReadinessProbe::class)->isReady($customer))->toBeTrue();
});

it('characterizes non-zero exit returns false without throwing', function (): void {
    $customer = characterizationProbeCustomer('char-probe-nz');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: '',
        stderr: 'not ready',
        exitCode: 1,
        parsedJson: null,
    ));
    bindCustomerReadinessProbeSsh($ssh);

    expect(app(CustomerReadinessProbe::class)->isReady($customer))->toBeFalse();
});

it('characterizes SshConnectionException returns false without rethrow', function (): void {
    $customer = characterizationProbeCustomer('char-probe-conn');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshConnectionException('down'));
    bindCustomerReadinessProbeSsh($ssh);

    expect(app(CustomerReadinessProbe::class)->isReady($customer))->toBeFalse();
});

it('characterizes SshTimeoutException returns false without rethrow', function (): void {
    $customer = characterizationProbeCustomer('char-probe-timeout');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshTimeoutException('timed out'));
    bindCustomerReadinessProbeSsh($ssh);

    expect(app(CustomerReadinessProbe::class)->isReady($customer))->toBeFalse();
});

it('characterizes inactive cluster returns false without SSH call', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'offline']);
    $customer = Customer::create([
        'slug' => 'char-probe-offline',
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-probe-offline.example.com',
        'status' => 'provisioning_finishing',
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    bindCustomerReadinessProbeSsh($ssh);

    expect(app(CustomerReadinessProbe::class)->isReady($customer))->toBeFalse();
});
