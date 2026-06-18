<?php

declare(strict_types=1);

use App\Http\Livewire\ClusterServers\Index;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use Livewire\Livewire;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('run');
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);
});

it('characterizes testConnection ping with timeout 10 updates cluster to active', function (): void {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['status' => 'unreachable']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('ping')
        ->once()
        ->withArgs(fn (ClusterServer $c, int $timeout) => $c->id === $cluster->id && $timeout === 10)
        ->andReturn(new SshResponse(
            stdout: "nextcloud-manage 1.0.0\n",
            stderr: '',
            exitCode: 0,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('testConnection', $cluster->id)
        ->assertDispatched('toast', type: 'success');

    $cluster->refresh();
    expect($cluster->status)->toBe('active')
        ->and($cluster->last_health_at)->not->toBeNull();
});

it('characterizes testConnection SshTimeoutException marks unreachable with timeout toast', function (): void {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('ping')
        ->once()
        ->andThrow(new SshTimeoutException('Timeout'));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('testConnection', $cluster->id)
        ->assertDispatched('toast', type: 'error', msg: 'Timeout ao conectar');

    $cluster->refresh();
    expect($cluster->status)->toBe('unreachable');
});

it('characterizes testConnection SshConnectionException marks unreachable with error toast', function (): void {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('ping')
        ->once()
        ->andThrow(new SshConnectionException('Connection refused'));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('testConnection', $cluster->id)
        ->assertDispatched('toast', type: 'error');

    $cluster->refresh();
    expect($cluster->status)->toBe('unreachable');
});
