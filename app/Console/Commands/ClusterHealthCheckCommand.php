<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Integration\Dto\ProbeClusterHealthCommand;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Console\Command;

class ClusterHealthCheckCommand extends Command
{
    protected $signature = 'cluster:health-check';

    protected $description = 'Run SSH health check on all active cluster servers and update status + last_health_at';

    public function __construct(private readonly PlatformPortFactory $factory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $clusters = ClusterServer::all();

        foreach ($clusters as $cluster) {
            try {
                $report = $this->factory->for($cluster)->probeClusterHealth(
                    new ProbeClusterHealthCommand($cluster, 10),
                );
                $status = $report->exitCode === 0 ? 'active' : 'unreachable';
            } catch (SshClientException) {
                $status = 'unreachable';
            }

            $cluster->update(['status' => $status, 'last_health_at' => now()]);
            $this->line("[{$cluster->name}] → {$status}");
        }

        return self::SUCCESS;
    }
}
