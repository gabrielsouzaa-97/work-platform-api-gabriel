<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Dto\SyncReport;
use App\Modules\Customers\Services\CustomerSyncService;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('run');
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);
});

function characterizationSyncCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['name' => 'char-sync-cluster', 'status' => 'active']);
}

it('characterizes sync argv: list --json with 30s timeout', function (): void {
    $cluster = characterizationSyncCluster();
    $payload = [
        'schema_version' => '1',
        'instances' => [
            ['name' => 'char-sync-new', 'domain' => 'char-sync-new.example.com', 'status' => 'running'],
        ],
        'shared_services' => [],
    ];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args, ?string $stdin, int $timeout) use ($cluster): bool {
            return $c->id === $cluster->id
                && $cmd === 'nextcloud-manage'
                && $args === ['list', '--json']
                && $stdin === null
                && $timeout === 30;
        })
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $report = app(CustomerSyncService::class)->sync($cluster);

    expect($report)->toBeInstanceOf(SyncReport::class)
        ->and($report->inserted)->toBe(1);
});

it('characterizes non-zero exit returns empty SyncReport without throwing', function (): void {
    $cluster = characterizationSyncCluster();

    Customer::create([
        'slug' => 'char-sync-unchanged',
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-sync-unchanged.example.com',
        'status' => 'active',
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andReturn(new SshResponse(stdout: '', stderr: 'error', exitCode: 1, parsedJson: null));

    app()->instance(SshClientInterface::class, $ssh);

    $report = app(CustomerSyncService::class)->sync($cluster);

    expect($report->inserted)->toBe(0)
        ->and($report->updated)->toBe(0)
        ->and($report->deleted)->toBe(0)
        ->and(Customer::find('char-sync-unchanged')->status)->toBe('active');
});

it('characterizes SshConnectionException propagates from sync', function (): void {
    $cluster = characterizationSyncCluster();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshConnectionException('Connection refused'));

    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(CustomerSyncService::class)->sync($cluster))
        ->toThrow(SshConnectionException::class);
});

it('characterizes running upstream status maps to local active on insert', function (): void {
    $cluster = characterizationSyncCluster();
    $payload = [
        'schema_version' => '1',
        'instances' => [
            ['name' => 'char-sync-running', 'domain' => 'char-sync-running.example.com', 'status' => 'running'],
        ],
        'shared_services' => [],
    ];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: json_encode($payload),
        stderr: '',
        exitCode: 0,
        parsedJson: $payload,
    ));

    app()->instance(SshClientInterface::class, $ssh);

    app(CustomerSyncService::class)->sync($cluster);

    expect(Customer::find('char-sync-running')->status)->toBe('active');
});
