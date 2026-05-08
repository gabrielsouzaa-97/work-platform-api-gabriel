<?php

declare(strict_types=1);

namespace App\Modules\Core\Ssh;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Dto\SshResponse;

interface SshClientInterface
{
    public function run(
        ClusterServer $cluster,
        string $cmd,
        array $args = [],
        ?string $payloadStdin = null,
        int $timeoutSec = 60
    ): SshResponse;

    public function runAsync(
        ClusterServer $cluster,
        string $cmd,
        array $args = [],
        ?string $payloadStdin = null
    ): SshResponse;

    public function scpUpload(ClusterServer $cluster, string $localPath, string $remotePath): void;
}
