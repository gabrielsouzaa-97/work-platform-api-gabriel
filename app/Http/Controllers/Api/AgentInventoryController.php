<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmAgent;
use App\Modules\Farms\Services\FarmInventoryIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AgentInventoryController extends Controller
{
    public function __construct(
        private readonly FarmInventoryIngestService $ingestService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        /** @var FarmAgent $agent */
        $agent = $request->attributes->get('farm_agent');

        $validated = $request->validate([
            'schema_version' => ['sometimes', 'integer'],
            'operation' => ['sometimes', 'string'],
            'farm_id' => ['sometimes', 'string'],
            'reported_at' => ['sometimes', 'string'],
            'capacity' => ['required', 'array'],
            'capacity.max_tenants' => ['required', 'integer', 'min:0'],
            'capacity.active_tenants' => ['sometimes', 'integer', 'min:0'],
            'capacity.available_slots' => ['sometimes', 'integer', 'min:0'],
            'versions' => ['sometimes', 'array'],
            'versions.platform' => ['sometimes', 'string'],
            'latency_ms' => ['sometimes', 'integer', 'min:0'],
            'tenants' => ['sometimes', 'array'],
        ]);

        $inventory = $this->ingestService->ingest($agent, $validated);

        return response()->json([
            'data' => [
                'farm_id' => $inventory->farm_id,
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
