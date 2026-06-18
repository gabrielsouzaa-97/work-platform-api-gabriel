<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Actions\RemoveCustomerAction;
use App\Modules\Customers\Exceptions\StateConflictException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

it('characterizes remove tenant dispatch via PlatformPort SSH', function (): void {
    Config::set('services.agent.transport_enabled', false);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create(['role' => 'admin', 'status' => 'active']);
    $customer = Customer::create([
        'slug' => 'char-remove-ssh',
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-remove-ssh.example.com',
        'status' => 'active',
    ]);
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args) use ($cluster): bool {
            return $c->id === $cluster->id
                && $cmd === 'nextcloud-manage'
                && in_array('remove', $args, true);
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);

    $job = app(RemoveCustomerAction::class)->execute(
        $customer->slug,
        $customer->slug,
        true,
        $operator,
    );

    expect($job->job_id)->toBe($jobId)
        ->and($customer->fresh()->status)->toBe('removing');
});

it('characterizes port state conflict maps to StateConflictException', function (): void {
    Config::set('services.agent.transport_enabled', false);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create(['role' => 'admin', 'status' => 'active']);
    $customer = Customer::create([
        'slug' => 'char-remove-conflict',
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-remove-conflict.example.com',
        'status' => 'active',
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andThrow(new SshRemoteException('state conflict', remoteExitCode: 4, stateConflict: true, parsedJson: ['diff' => ['status' => 'active']]));
    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(RemoveCustomerAction::class)->execute(
        $customer->slug,
        $customer->slug,
        false,
        $operator,
    ))->toThrow(StateConflictException::class);
});
