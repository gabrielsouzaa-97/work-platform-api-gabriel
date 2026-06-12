<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmAgent;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentCommandQueue;
use App\Modules\Agents\Services\FarmAgentRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Operator CRUD for farm agent registry (Sprint N18).
 */
final class FarmAgentController extends Controller
{
    public function __construct(
        private readonly FarmAgentRegistry $registry,
        private readonly AgentCommandQueue $commandQueue,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('manage-cluster-servers');

        $agents = FarmAgent::query()
            ->with('clusterServer:id,name,status')
            ->orderBy('farm_id')
            ->get()
            ->map(fn (FarmAgent $a): array => $this->serialize($a));

        return response()->json(['data' => $agents]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-cluster-servers');

        $validated = $request->validate([
            'farm_id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9-]{0,62}$/'],
            'cluster_server_id' => ['nullable', 'uuid', 'exists:cluster_servers,id'],
            'mtls_cert_fingerprint' => ['nullable', 'string', 'max:128'],
        ]);

        /** @var Operator $actor */
        $actor = $request->user();

        $result = $this->registry->register(
            $validated['farm_id'],
            $validated['cluster_server_id'] ?? null,
            $validated['mtls_cert_fingerprint'] ?? null,
            $actor,
        );

        return response()->json([
            'data' => $this->serialize($result['farmAgent']),
            'agent_token' => $result['rawToken'],
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        Gate::authorize('manage-cluster-servers');

        $agent = FarmAgent::query()->with('clusterServer:id,name,status')->findOrFail($id);

        return response()->json(['data' => $this->serialize($agent)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        Gate::authorize('manage-cluster-servers');

        $agent = FarmAgent::query()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['sometimes', 'in:active,revoked'],
            'mtls_cert_fingerprint' => ['nullable', 'string', 'max:128'],
            'cluster_server_id' => ['nullable', 'uuid', 'exists:cluster_servers,id'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'revoked') {
            /** @var Operator $actor */
            $actor = $request->user();
            $agent = $this->registry->revoke($agent, $actor);
        } else {
            $agent->update(collect($validated)->except('status')->all());
        }

        return response()->json(['data' => $this->serialize($agent->fresh())]);
    }

    public function enqueuePing(Request $request, string $id): JsonResponse
    {
        Gate::authorize('manage-cluster-servers');

        $agent = FarmAgent::query()->findOrFail($id);
        $command = $this->commandQueue->enqueue($agent, 'agent.ping');

        return response()->json([
            'operation_id' => $command->operation_id,
            'operation' => $command->operation,
        ], 202);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(FarmAgent $agent): array
    {
        return [
            'id' => $agent->id,
            'farm_id' => $agent->farm_id,
            'cluster_server_id' => $agent->cluster_server_id,
            'cluster_server' => $agent->relationLoaded('clusterServer') ? $agent->clusterServer : null,
            'mtls_cert_fingerprint' => $agent->mtls_cert_fingerprint,
            'status' => $agent->status,
            'online' => $agent->isOnline(),
            'last_seen_at' => $agent->last_seen_at?->toIso8601String(),
            'created_at' => $agent->created_at?->toIso8601String(),
        ];
    }
}
