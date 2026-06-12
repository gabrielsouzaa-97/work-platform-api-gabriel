<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\FarmAgent;
use App\Modules\Agents\Services\AgentTransportResolver;
use Illuminate\Support\Facades\Config;

it('returns false when transport flag is disabled', function (): void {
    Config::set('services.agent.transport_enabled', false);

    $cluster = ClusterServer::factory()->create();
    FarmAgent::factory()->create(['cluster_server_id' => $cluster->id]);

    $resolver = app(AgentTransportResolver::class);

    expect($resolver->shouldUseAgentTransport($cluster))->toBeFalse();
});

it('returns true when flag enabled and agent is online', function (): void {
    Config::set('services.agent.transport_enabled', true);

    $cluster = ClusterServer::factory()->create();
    FarmAgent::factory()->create([
        'cluster_server_id' => $cluster->id,
        'last_seen_at' => now(),
    ]);

    $resolver = app(AgentTransportResolver::class);

    expect($resolver->shouldUseAgentTransport($cluster))->toBeTrue();
});

it('returns false when agent is offline', function (): void {
    Config::set('services.agent.transport_enabled', true);

    $cluster = ClusterServer::factory()->create();
    FarmAgent::factory()->offline()->create([
        'cluster_server_id' => $cluster->id,
    ]);

    $resolver = app(AgentTransportResolver::class);

    expect($resolver->shouldUseAgentTransport($cluster))->toBeFalse();
});
