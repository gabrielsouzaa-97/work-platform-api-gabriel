<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Dto\ProbeReadinessCommand;
use App\Modules\Jobs\Services\TransportObservability;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

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
});

afterEach(function (): void {
    Http::swap(new Factory);
    resetCustomerReadinessProbeContainer();
    Mockery::close();
});

function characterizationProbeCustomer(string $slug = 'char-probe-acme'): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'provisioning_finishing',
        'image_mode' => false,
    ]);
}

function characterizationProbeCustomerInMemory(string $slug, string $clusterStatus = 'active'): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => $clusterStatus]);
    $customer = new Customer([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'provisioning_finishing',
        'image_mode' => false,
    ]);
    $customer->setRelation('clusterServer', $cluster);

    return $customer;
}

function probeReadinessWithSsh(Customer $customer, SshClientInterface $ssh): bool
{
    $observability = new TransportObservability;
    $adapter = new SshPlatformAdapter($ssh, $observability);

    return $adapter->probeReadiness(new ProbeReadinessCommand($customer))->ready;
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

// SSH failure paths (non-zero exit, connection/timeout exceptions) are covered in tests/Feature/Customers/CustomerReadinessTest.php and probeReadiness exception wrapping in SshPlatformAdapter.

it('characterizes inactive cluster returns false without SSH call', function (): void {
    $customer = characterizationProbeCustomerInMemory('char-probe-offline', 'offline');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');

    expect(probeReadinessWithSsh($customer, $ssh))->toBeFalse();
});

it('characterizes readiness gates include app:list, user:list, and memail config', function (): void {
    $customer = characterizationProbeCustomer();

    Http::swap(new Factory);
    fakeReadinessGateR6Http($customer->domain);

    expect(makeCustomerReadinessProbeWithSsh(characterizationReadinessGateMock())->isReady($customer))->toBeTrue();
});

it('characterizes all gates passing returns true', function (): void {
    $customer = characterizationProbeCustomer('char-probe-ok');

    Http::swap(new Factory);
    fakeReadinessGateR6Http($customer->domain);

    expect(makeCustomerReadinessProbeWithSsh(characterizationReadinessGateMock())->isReady($customer))->toBeTrue();
});
