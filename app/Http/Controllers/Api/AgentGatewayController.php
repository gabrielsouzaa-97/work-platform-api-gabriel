<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmAgent;
use App\Modules\Agents\Services\AgentCommandQueue;
use App\Modules\Agents\Services\AgentEventHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Farm Agent gateway — outbound poll + event ingress (Platform V2 / Sprint N18).
 */
final class AgentGatewayController extends Controller
{
    public function __construct(
        private readonly AgentCommandQueue $commandQueue,
        private readonly AgentEventHandler $eventHandler,
    ) {}

    /**
     * Long-poll pending commands for the authenticated farm agent.
     */
    public function pollCommands(Request $request): JsonResponse|Response
    {
        /** @var FarmAgent $agent */
        $agent = $request->attributes->get('farm_agent');

        $timeout = (int) $request->query('timeout', (string) config('services.agent.poll_timeout_seconds', 55));
        $commands = $this->commandQueue->poll($agent, $timeout);

        if ($commands === []) {
            return response()->noContent();
        }

        return response()->json([
            'schema_version' => 1,
            'commands' => $commands,
        ]);
    }

    /**
     * Receive heartbeat/progress events from the farm agent.
     */
    public function receiveEvents(Request $request): JsonResponse
    {
        /** @var FarmAgent $agent */
        $agent = $request->attributes->get('farm_agent');

        $validated = $request->validate([
            'schema_version' => ['sometimes', 'integer'],
            'operation_id' => ['sometimes', 'uuid'],
            'farm_id' => ['sometimes', 'string'],
            'state' => ['sometimes', 'string'],
            'step' => ['sometimes', 'string'],
            'message' => ['sometimes', 'string'],
            'percent' => ['sometimes', 'integer'],
            'ts' => ['sometimes', 'string'],
            'event_type' => ['sometimes', 'string'],
            'data' => ['sometimes', 'array'],
            'data.job_id' => ['sometimes', 'string'],
        ]);

        $this->eventHandler->handle($agent, $validated);

        return response()->json(['accepted' => true], Response::HTTP_ACCEPTED);
    }
}
