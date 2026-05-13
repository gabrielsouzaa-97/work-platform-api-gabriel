<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Services;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Job;
use App\Modules\Core\Translators\StateTranslator;
use App\Modules\Jobs\Dto\WebhookPayload;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WebhookHandler
{
    public function __construct(
        private readonly StateTranslator $stateTranslator,
    ) {}

    public function handle(ClusterServer $cluster, array $rawPayload): void
    {
        $payload = WebhookPayload::fromArray($rawPayload);

        $job = Job::find($payload->jobId);

        if (! $job) {
            throw (new ModelNotFoundException)->setModel(Job::class, $payload->jobId);
        }

        if ($job->cluster_server_id !== $cluster->id) {
            throw new \DomainException("Job {$payload->jobId} belongs to a different cluster.");
        }

        $canonical = $this->stateTranslator->toCanonical($payload->state);

        DB::transaction(function () use ($job, $canonical, $payload, $cluster): void {
            $job->update([
                'state' => $canonical,
                'callback_received_at' => now(),
                'finished_at' => Carbon::parse($payload->finishedAt),
                'exit_code' => $payload->exitCode,
            ]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => null,
                'action' => 'webhook_received',
                'resource_type' => 'job',
                'resource_id' => $job->job_id,
                'payload' => [
                    'state' => $canonical,
                    'cmd' => $payload->cmd,
                    'exit_code' => $payload->exitCode,
                ],
                'cluster_server_id' => $cluster->id,
                'job_id' => $job->job_id,
                'ip' => null,
            ]);
        });
    }
}
