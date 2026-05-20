<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Services;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Core\Translators\StateTranslator;
use App\Modules\Jobs\Dto\WebhookPayload;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WebhookHandler
{
    private const TERMINAL_STATES = ['success', 'failed', 'cancelled'];

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

        // Out-of-order guard: never overwrite a terminal state with `running`.
        // The upstream worker may deliver `job.started` AFTER `job.finished` if
        // the started callback was retried after the worker had already moved on
        // (the dedupe lives in our cache, not the upstream queue, so a missed
        // 2xx upstream-side can trigger a late retry). The job has already
        // converged; we acknowledge the delivery silently.
        if ($canonical === 'running' && in_array($job->state, self::TERMINAL_STATES, true)) {
            return;
        }

        if ($payload->isStarted()) {
            $this->applyStartedEvent($job, $canonical, $payload, $cluster);

            return;
        }

        $this->applyFinishedEvent($job, $canonical, $payload, $cluster);
    }

    /**
     * `job.started` lifecycle event — sets state=running and started_at, never
     * touches finished_at/exit_code or Customer.status. Safe to re-apply if the
     * job already reflects the same state (no-op write avoided).
     */
    private function applyStartedEvent(
        Job $job,
        string $canonical,
        WebhookPayload $payload,
        ClusterServer $cluster,
    ): void {
        $alreadyApplied = $job->state === $canonical && $job->started_at !== null;
        if ($alreadyApplied) {
            return;
        }

        DB::transaction(function () use ($job, $canonical, $payload, $cluster): void {
            $updates = [
                'state' => $canonical,
                'callback_received_at' => now(),
            ];

            if ($payload->startedAt !== null && $job->started_at === null) {
                $updates['started_at'] = Carbon::parse($payload->startedAt);
            }

            $job->update($updates);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => null,
                'action' => 'webhook_received',
                'resource_type' => 'job',
                'resource_id' => $job->job_id,
                'payload' => [
                    'event' => $payload->event,
                    'state' => $canonical,
                    'cmd' => $payload->cmd ?? $job->job_type,
                ],
                'cluster_server_id' => $cluster->id,
                'job_id' => $job->job_id,
                'ip' => null,
            ]);
        });
    }

    /**
     * `job.finished` lifecycle event (or pre-event legacy callbacks). Persists
     * the terminal state, finished_at, exit_code and — for terminal canonical
     * states — propagates the customer.status transition.
     */
    private function applyFinishedEvent(
        Job $job,
        string $canonical,
        WebhookPayload $payload,
        ClusterServer $cluster,
    ): void {
        if ($job->state === $canonical) {
            return;
        }

        DB::transaction(function () use ($job, $canonical, $payload, $cluster): void {
            $updates = [
                'state' => $canonical,
                'callback_received_at' => now(),
                'exit_code' => $payload->exitCode,
            ];

            if ($payload->finishedAt !== null) {
                $updates['finished_at'] = Carbon::parse($payload->finishedAt);
            }

            $job->update($updates);

            // Propagate terminal job states to the owning Customer.
            if ($job->customer_slug && in_array($canonical, self::TERMINAL_STATES, true)) {
                $customerStatus = match (true) {
                    $job->job_type === 'provision' && $canonical === 'success' => 'active',
                    $job->job_type === 'provision' && in_array($canonical, ['failed', 'cancelled'], true) => 'failed',
                    $job->job_type === 'deprovision' && $canonical === 'success' => 'removed',
                    default => null,
                };

                if ($customerStatus !== null) {
                    Customer::where('slug', $job->customer_slug)
                        ->update(['status' => $customerStatus]);
                }
            }

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => null,
                'action' => 'webhook_received',
                'resource_type' => 'job',
                'resource_id' => $job->job_id,
                'payload' => [
                    'event' => $payload->event,
                    'state' => $canonical,
                    'cmd' => $payload->cmd ?? $job->job_type,
                    'exit_code' => $payload->exitCode,
                    'duration_ms' => $payload->durationMs,
                ],
                'cluster_server_id' => $cluster->id,
                'job_id' => $job->job_id,
                'ip' => null,
            ]);
        });
    }
}
