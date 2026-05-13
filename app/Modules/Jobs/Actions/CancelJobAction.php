<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Actions;

use App\Models\AuditLog;
use App\Models\Job;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CancelJobAction
{
    public function __construct(
        private readonly SshClientInterface $ssh,
    ) {}

    /**
     * Sends cancel command to upstream and updates the local job state.
     *
     * @throws SshClientException
     * @throws \DomainException if job is not in a cancellable state
     */
    public function execute(Job $job, ?string $actorId = null): void
    {
        if (! in_array($job->state, ['queued', 'running'], true)) {
            throw new \DomainException(
                "Job [{$job->job_id}] cannot be cancelled from state [{$job->state}]."
            );
        }

        $cluster = $job->clusterServer;

        $this->ssh->run(
            cluster: $cluster,
            cmd: 'nextcloud-manage',
            args: ['job', $job->job_id, 'cancel', '--json'],
        );

        DB::transaction(function () use ($job, $actorId): void {
            $job->update(['state' => 'cancelled']);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actorId,
                'action' => 'job.cancel',
                'resource_type' => 'job',
                'resource_id' => $job->job_id,
                'payload' => ['previous_state' => $job->getOriginal('state')],
                'cluster_server_id' => $job->cluster_server_id,
                'job_id' => $job->job_id,
            ]);
        });
    }
}
