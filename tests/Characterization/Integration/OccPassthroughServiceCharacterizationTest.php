<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Services\OccPassthroughService;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('run');
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);
});

function characterizationOccCustomer(string $slug = 'char-occ-acme'): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

it('characterizes occ-exec argv: slug, occ-exec, subcmd, extra args, --json', function (): void {
    $customer = characterizationOccCustomer();
    $payload = ['exit_code' => 0, 'stdout' => 'ok'];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args, ?string $stdin, int $timeout) use ($customer): bool {
            return $cmd === 'nextcloud-manage'
                && $args === [$customer->slug, 'occ-exec', 'user:list', '--limit', '5', '--json']
                && $stdin === null
                && $timeout === 45;
        })
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $result = app(OccPassthroughService::class)->exec($customer, 'user:list', ['--limit', '5'], 45);

    expect($result)->toBe($payload);
});

it('characterizes exec returns parsedJson array from SSH response', function (): void {
    $customer = characterizationOccCustomer('char-occ-json');
    $payload = ['schema_version' => '1', 'users' => ['alice']];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: json_encode($payload),
        stderr: '',
        exitCode: 0,
        parsedJson: $payload,
    ));

    app()->instance(SshClientInterface::class, $ssh);

    $result = app(OccPassthroughService::class)->exec($customer, 'user:list');

    expect($result)->toBeArray()->toBe($payload);
});

it('characterizes SshConnectionException maps to ClusterUnreachableException', function (): void {
    $customer = characterizationOccCustomer('char-occ-conn');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshConnectionException('timeout'));
    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(OccPassthroughService::class)->exec($customer, 'user:list'))
        ->toThrow(ClusterUnreachableException::class);
});

it('characterizes inactive cluster throws ClusterUnreachableException without SSH', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'offline']);
    $customer = Customer::create([
        'slug' => 'char-occ-offline',
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-occ-offline.example.com',
        'status' => 'active',
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(OccPassthroughService::class)->exec($customer, 'user:list'))
        ->toThrow(ClusterUnreachableException::class);
});

it('characterizes invalid JSON stdout throws RuntimeException', function (): void {
    $customer = characterizationOccCustomer('char-occ-badjson');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: 'not-json',
        stderr: '',
        exitCode: 0,
        parsedJson: null,
    ));

    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(OccPassthroughService::class)->exec($customer, 'user:list'))
        ->toThrow(RuntimeException::class);
});

it('characterizes SshRemoteException propagates unchanged', function (): void {
    $customer = characterizationOccCustomer('char-occ-remote');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andThrow(new SshRemoteException('subcmd blocked', remoteExitCode: 16));

    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(OccPassthroughService::class)->exec($customer, 'user:delete'))
        ->toThrow(SshRemoteException::class);
});

it('characterizes execThemingConfig invokes one occ-exec per key-value pair', function (): void {
    $customer = characterizationOccCustomer('char-occ-theme');
    $payload = ['exit_code' => 0];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && $args === [$customer->slug, 'occ-exec', 'theming:config', 'name', 'Acme', '--json'])
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && $args === [$customer->slug, 'occ-exec', 'theming:config', 'color', '#ff0000', '--json'])
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));

    app()->instance(SshClientInterface::class, $ssh);

    app(OccPassthroughService::class)->execThemingConfig($customer, [
        'name' => 'Acme',
        'color' => '#ff0000',
    ]);
});
