<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\FarmAgent;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function characterizationProvisionSuccess(string $jobId): SshResponse
{
    return new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    );
}

it('characterizes provision dispatch via SSH when agent transport is disabled', function (): void {
    Config::set('services.agent.transport_enabled', false);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args): bool {
            return $c->status === 'active'
                && $cmd === 'nextcloud-manage'
                && in_array('create', $args, true);
        })
        ->andReturn(characterizationProvisionSuccess($jobId));
    app()->instance(SshClientInterface::class, $sshMock);

    $gatewayMock = Mockery::mock(AgentUpstreamGateway::class);
    $gatewayMock->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gatewayMock);

    $result = app(ProvisionCustomerAction::class)->execute(
        new ProvisionPayload(
            slug: 'char-ssh-provision',
            clusterServerId: $cluster->id,
            domain: 'char-ssh-provision.example.com',
            apps: [],
            fullApps: false,
            logoPath: null,
            backgroundPath: null,
        ),
        $operator,
    );

    expect($result['job']->job_id)->toBe($jobId);
});

it('characterizes provision dispatch via agent when transport enabled without staging', function (): void {
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
        ->withArgs(fn (ClusterServer $c, string $cmd, array $args): bool => $cmd === 'nextcloud-manage'
            && in_array('create', $args, true))
        ->andReturn(characterizationProvisionSuccess($jobId));
    app()->instance(AgentUpstreamGateway::class, $gatewayMock);

    $result = app(ProvisionCustomerAction::class)->execute(
        new ProvisionPayload(
            slug: 'char-agent-provision',
            clusterServerId: $cluster->id,
            domain: 'char-agent-provision.example.com',
            apps: [],
            fullApps: false,
            logoPath: null,
            backgroundPath: null,
        ),
        $operator,
    );

    expect($result['job']->job_id)->toBe($jobId);
});

it('characterizes factory staging rule forces SSH even when agent transport is enabled', function (): void {
    Config::set('services.agent.transport_enabled', true);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'cluster_server_id' => $cluster->id,
        'last_seen_at' => now(),
    ]);

    $factory = app(PlatformPortFactory::class);

    expect($factory->shouldUseAgentTransport($cluster, null))->toBeTrue();
    expect($factory->shouldUseAgentTransport($cluster, (string) Str::uuid()))->toBeFalse();
});
