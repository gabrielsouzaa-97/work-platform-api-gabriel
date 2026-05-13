<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Console\Command;

class ClusterHealthCheckCommand extends Command
{
    protected $signature = 'cluster:health-check';

    protected $description = 'Run SSH health check on all active cluster servers and update status + last_health_at';

    public function __construct(private readonly SshClientInterface $ssh)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $clusters = ClusterServer::all();

        foreach ($clusters as $cluster) {
            try {
                $expected = "healthcheck-{$cluster->id}";
                $resp = $this->ssh->run($cluster, 'echo', [$expected], null, 10);

                $status = (trim($resp->stdout) === $expected && $resp->exitCode === 0)
                    ? 'active'
                    : 'unreachable';
            } catch (SshClientException) {
                $status = 'unreachable';
            }

            $cluster->update(['status' => $status, 'last_health_at' => now()]);
            $this->line("[{$cluster->name}] → {$status}");
        }

        return self::SUCCESS;
    }
}
