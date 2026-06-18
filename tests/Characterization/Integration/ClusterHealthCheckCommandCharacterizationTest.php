<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('run');
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);
});

it('characterizes health check ping with timeout 10 marks cluster active on exit 0', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'unreachable', 'name' => 'char-health-ok']);

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

    $this->artisan('cluster:health-check')->assertSuccessful();

    $cluster->refresh();
    expect($cluster->status)->toBe('active')
        ->and($cluster->last_health_at)->not->toBeNull();
});

it('characterizes health check non-zero ping exit marks cluster unreachable', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active', 'name' => 'char-health-bad']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('ping')
        ->once()
        ->andReturn(new SshResponse(stdout: '', stderr: 'fail', exitCode: 101));
    app()->instance(SshClientInterface::class, $ssh);

    $this->artisan('cluster:health-check')->assertSuccessful();

    $cluster->refresh();
    expect($cluster->status)->toBe('unreachable');
});

it('characterizes health check SshClientException marks cluster unreachable', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active', 'name' => 'char-health-down']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('ping')
        ->once()
        ->andThrow(new SshConnectionException('Connection refused'));
    app()->instance(SshClientInterface::class, $ssh);

    $this->artisan('cluster:health-check')->assertSuccessful();

    $cluster->refresh();
    expect($cluster->status)->toBe('unreachable');
});
