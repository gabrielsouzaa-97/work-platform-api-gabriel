<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\FarmAgent;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Actions\RemoveCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

function agentTransportJobResponse(string $jobId): SshResponse
{
    return new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    );
}

it('provision uses agent gateway when transport enabled and farm online', function (): void {
    Config::set('services.agent.transport_enabled', true);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'cluster_server_id' => $cluster->id,
        'last_seen_at' => now(),
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $sshMock);

    $gatewayMock = Mockery::mock(AgentUpstreamGateway::class);
    $gatewayMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args): bool {
            return $c->status === 'active'
                && $cmd === 'nextcloud-manage'
                && in_array('create', $args, true);
        })
        ->andReturn(agentTransportJobResponse($jobId));
    app()->instance(AgentUpstreamGateway::class, $gatewayMock);

    $result = app(ProvisionCustomerAction::class)->execute(
        new ProvisionPayload(
            slug: 'agent-pilot',
            clusterServerId: $cluster->id,
            domain: 'agent-pilot.example.com',
            apps: [],
            fullApps: false,
            logoPath: null,
            backgroundPath: null,
        ),
        $operator,
    );

    expect($result['job']->job_id)->toBe($jobId);
});

it('remove uses agent gateway when transport enabled and farm online', function (): void {
    Config::set('services.agent.transport_enabled', true);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'cluster_server_id' => $cluster->id,
        'last_seen_at' => now(),
    ]);
    $operator = Operator::factory()->admin()->create();
    $jobId = Str::uuid()->toString();

    $customer = \App\Models\Customer::create([
        'slug' => 'remove-agent',
        'cluster_server_id' => $cluster->id,
        'domain' => 'remove-agent.example.com',
        'status' => 'active',
    ]);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $sshMock);

    $gatewayMock = Mockery::mock(AgentUpstreamGateway::class);
    $gatewayMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args): bool {
            return $cmd === 'nextcloud-manage' && in_array('remove', $args, true);
        })
        ->andReturn(agentTransportJobResponse($jobId));
    app()->instance(AgentUpstreamGateway::class, $gatewayMock);

    $job = app(RemoveCustomerAction::class)->execute(
        slug: $customer->slug,
        confirmSlug: $customer->slug,
        backupFirst: false,
        actor: $operator,
    );

    expect($job->job_id)->toBe($jobId);
});

it('provision falls back to ssh when agent transport flag is off', function (): void {
    Config::set('services.agent.transport_enabled', false);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'cluster_server_id' => $cluster->id,
        'last_seen_at' => now(),
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(agentTransportJobResponse($jobId));
    app()->instance(SshClientInterface::class, $sshMock);

    $gatewayMock = Mockery::mock(AgentUpstreamGateway::class);
    $gatewayMock->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gatewayMock);

    app(ProvisionCustomerAction::class)->execute(
        new ProvisionPayload(
            slug: 'ssh-fallback',
            clusterServerId: $cluster->id,
            domain: 'ssh-fallback.example.com',
            apps: [],
            fullApps: false,
            logoPath: null,
            backgroundPath: null,
        ),
        $operator,
    );
});
