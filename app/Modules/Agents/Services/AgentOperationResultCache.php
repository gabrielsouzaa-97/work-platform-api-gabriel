<?php

declare(strict_types=1);

namespace App\Modules\Agents\Services;

final class AgentOperationResultCache
{
    public static function key(string $operationId): string
    {
        return 'agent_op_result:'.$operationId;
    }
}
