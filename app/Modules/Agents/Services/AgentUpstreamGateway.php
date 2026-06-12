<?php

declare(strict_types=1);

namespace App\Modules\Agents\Services;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Dto\SshResponse;
use Illuminate\Support\Facades\Cache;

class AgentUpstreamGateway
{
    private const WAIT_SECONDS = 30;

    public function __construct(
        private readonly AgentTransportResolver $transportResolver,
        private readonly AgentCommandQueue $commandQueue,
    ) {}

    /**
     * Enqueue a manage.sh equivalent command on the farm agent and wait for job_id.
     *
     * @param  list<string>  $args
     */
    public function runAsync(
        ClusterServer $cluster,
        string $cmd,
        array $args,
        ?string $stdinJson = null,
    ): SshResponse {
        if ($cmd !== 'nextcloud-manage') {
            throw new \InvalidArgumentException('Agent transport only supports nextcloud-manage');
        }

        $agent = $this->transportResolver->findAgentForCluster($cluster);
        if ($agent === null) {
            throw new \RuntimeException('No active farm agent for cluster');
        }

        $operation = $this->resolveOperation($args);

        $command = $this->commandQueue->enqueue($agent, $operation, [
            'cmd' => $cmd,
            'args' => array_values($args),
            'stdin_json' => $stdinJson,
        ]);

        $jobId = $this->waitForJobId($command->operation_id);

        $parsed = ['job_id' => $jobId];

        return new SshResponse(
            json_encode($parsed, JSON_THROW_ON_ERROR),
            '',
            0,
            $parsed,
        );
    }

    public static function resultCacheKey(string $operationId): string
    {
        return 'agent_op_result:'.$operationId;
    }

    /**
     * @param  list<string>  $args
     */
    private function resolveOperation(array $args): string
    {
        if (in_array('create', $args, true)) {
            return 'tenant.create';
        }

        if (in_array('remove', $args, true)) {
            return 'tenant.remove';
        }

        throw new \InvalidArgumentException('Unsupported manage argv for agent transport');
    }

    private function waitForJobId(string $operationId): string
    {
        $cacheKey = self::resultCacheKey($operationId);
        $deadline = microtime(true) + self::WAIT_SECONDS;

        while (microtime(true) < $deadline) {
            /** @var array<string, mixed>|null $result */
            $result = Cache::get($cacheKey);

            if (is_array($result)) {
                if (isset($result['job_id']) && is_string($result['job_id']) && $result['job_id'] !== '') {
                    return $result['job_id'];
                }

                if (isset($result['error']) && is_string($result['error']) && $result['error'] !== '') {
                    throw new \RuntimeException($result['error']);
                }
            }

            usleep(100_000);
        }

        throw new \RuntimeException('Farm agent did not return job_id in time');
    }
}
