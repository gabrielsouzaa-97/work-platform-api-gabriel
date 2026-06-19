<?php

declare(strict_types=1);

use App\Models\AgentCommand;
use App\Models\ClusterServer;
use App\Models\FarmAgent;
use App\Modules\Agents\Exceptions\AgentTransportException;
use App\Modules\Agents\Services\AgentCommandQueue;
use App\Modules\Agents\Services\AgentTransportResolver;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

function gatewayWithMockedQueue(AgentCommand $command): AgentUpstreamGateway
{
    $queue = Mockery::mock(AgentCommandQueue::class);
    $queue->shouldReceive('enqueue')->once()->andReturn($command);

    return new AgentUpstreamGateway(
        app(AgentTransportResolver::class),
        $queue,
    );
}

it('returns job_id from operation cache after enqueue', function (): void {
    Config::set('services.agent.transport_enabled', true);
    Cache::flush();

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'cluster_server_id' => $cluster->id,
        'last_seen_at' => now(),
    ]);

    $operationId = Str::uuid()->toString();
    $jobId = Str::uuid()->toString();
    $command = AgentCommand::make([
        'farm_agent_id' => FarmAgent::first()->id,
        'operation_id' => $operationId,
        'operation' => 'tenant.create',
        'status' => 'pending',
    ]);

    $queue = Mockery::mock(AgentCommandQueue::class);
    $queue->shouldReceive('enqueue')
        ->once()
        ->andReturnUsing(function () use ($command, $operationId, $jobId): AgentCommand {
            Cache::put(
                AgentUpstreamGateway::resultCacheKey($operationId),
                ['job_id' => $jobId],
                120,
            );

            return $command;
        });

    $gateway = new AgentUpstreamGateway(app(AgentTransportResolver::class), $queue);

    $response = $gateway->runAsync($cluster, 'nextcloud-manage', ['create', '--async']);

    expect($response->parsedJson['job_id'] ?? null)->toBe($jobId);
});

it('throws when operation cache carries structured agent error', function (): void {
    Config::set('services.agent.transport_enabled', true);
    Cache::flush();

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'cluster_server_id' => $cluster->id,
        'last_seen_at' => now(),
    ]);

    $operationId = Str::uuid()->toString();
    $command = AgentCommand::make([
        'operation_id' => $operationId,
        'operation' => 'tenant.remove',
        'status' => 'pending',
    ]);

    $queue = Mockery::mock(AgentCommandQueue::class);
    $queue->shouldReceive('enqueue')
        ->once()
        ->andReturnUsing(function () use ($command, $operationId): AgentCommand {
            Cache::put(
                AgentUpstreamGateway::resultCacheKey($operationId),
                ['error' => 'upstream redis unavailable'],
                120,
            );

            return $command;
        });

    $gateway = new AgentUpstreamGateway(app(AgentTransportResolver::class), $queue);

    $gateway->runAsync($cluster, 'nextcloud-manage', ['remove', '--async']);
})->throws(AgentTransportException::class, 'upstream redis unavailable');

it('rejects unsupported manage argv for agent transport', function (): void {
    Config::set('services.agent.transport_enabled', true);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'cluster_server_id' => $cluster->id,
        'last_seen_at' => now(),
    ]);

    app(AgentUpstreamGateway::class)->runAsync(
        $cluster,
        'nextcloud-manage',
        ['users:create', '--async'],
    );
})->throws(InvalidArgumentException::class);

it('throws when no active farm agent exists for cluster', function (): void {
    Config::set('services.agent.transport_enabled', true);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    app(AgentUpstreamGateway::class)->runAsync(
        $cluster,
        'nextcloud-manage',
        ['create', '--async'],
    );
})->throws(AgentTransportException::class, 'No active farm agent for cluster');
