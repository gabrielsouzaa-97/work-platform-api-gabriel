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

        if ($job->state === $canonical) {
            return;
        }

        DB::transaction(function () use ($job, $canonical, $payload, $cluster): void {
            $job->update([
                'state' => $canonical,
                'callback_received_at' => now(),
                'finished_at' => Carbon::parse($payload->finishedAt),
                'exit_code' => $payload->exitCode,
            ]);

            // Propagate terminal job states to the owning Customer.
            if ($job->customer_slug && in_array($canonical, ['success', 'failed', 'cancelled'], true)) {
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
