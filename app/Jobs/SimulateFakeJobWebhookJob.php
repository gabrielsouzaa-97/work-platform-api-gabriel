<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClusterServer;
use App\Models\Job;
use App\Modules\Jobs\Services\WebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Simulates an upstream job.finished webhook when SSH_DRIVER=fake.
 */
final class SimulateFakeJobWebhookJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly string $jobId,
    ) {}

    public function handle(WebhookHandler $handler): void
    {
        if (config('services.ssh.driver') !== 'fake') {
            return;
        }

        $job = Job::query()->find($this->jobId);

        if ($job === null) {
            Log::warning('fake_ssh.webhook.job_missing', ['job_id' => $this->jobId]);

            return;
        }

        if (in_array($job->state, ['success', 'failed', 'cancelled'], true)) {
            return;
        }

        $cluster = ClusterServer::query()->find($job->cluster_server_id);

        if ($cluster === null) {
            Log::warning('fake_ssh.webhook.cluster_missing', [
                'job_id' => $this->jobId,
                'cluster_id' => $job->cluster_server_id,
            ]);

            return;
        }

        $handler->handle($cluster, [
            'event' => 'job.finished',
            'job_id' => $this->jobId,
            'state' => 'success',
            'exit_code' => 0,
            'finished_at' => now()->toIso8601String(),
        ]);
    }
}
