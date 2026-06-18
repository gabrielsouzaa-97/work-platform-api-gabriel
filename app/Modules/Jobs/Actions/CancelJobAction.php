<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Actions;

use App\Models\AuditLog;
use App\Models\Job;
use App\Modules\Integration\Dto\CancelJobCommand;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CancelJobAction
{
    public function __construct(
        private readonly PlatformPortFactory $factory,
    ) {}

    /**
     * Sends cancel command to upstream and updates the local job state.
     *
     * @throws UpstreamUnavailableException
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

        $this->factory->for($cluster)->cancelJob(new CancelJobCommand($job, $actorId));

        DB::transaction(function () use ($job, $actorId): void {
            $previousState = $job->state;
            $job->update(['state' => 'cancelled']);

            $payload = ['previous_state' => $previousState];
            if ($job->correlation_id !== null && $job->correlation_id !== '') {
                $payload['correlation_id'] = $job->correlation_id;
            }

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actorId,
                'action' => 'job.cancel',
                'resource_type' => 'job',
                'resource_id' => $job->job_id,
                'payload' => $payload,
                'cluster_server_id' => $job->cluster_server_id,
                'job_id' => $job->job_id,
            ]);
        });
    }
}
