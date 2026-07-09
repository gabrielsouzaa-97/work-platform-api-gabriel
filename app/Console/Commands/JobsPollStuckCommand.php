<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Job;
use App\Modules\Core\Translators\Exceptions\UnknownStateException;
use App\Modules\Core\Translators\StateTranslator;
use App\Modules\Integration\Dto\PollJobStatusCommand;
use App\Modules\Integration\Services\PlatformPortFactory;
use App\Modules\Jobs\Services\TransportObservability;
use App\Modules\Jobs\Services\WebhookHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobsPollStuckCommand extends Command
{
    private const TERMINAL_STATES = ['success', 'failed', 'cancelled'];

    protected $signature = 'jobs:poll-stuck';

    protected $description = 'Poll upstream for jobs stuck in running state with no callback after 60s';

    public function handle(
        PlatformPortFactory $factory,
        StateTranslator $st,
        TransportObservability $observability,
        WebhookHandler $webhookHandler,
    ): int {
        $slaSeconds = $observability->stuckJobSlaSeconds();
        $stuck = Job::query()
            ->where('state', 'running')
            ->whereNull('callback_received_at')
            ->where('queued_at', '<', now()->subSeconds($slaSeconds))
            ->limit(50)
            ->with('clusterServer')
            ->get();

        foreach ($stuck as $job) {
            $observability->alertJobStuckBeyondSla($job);

            $cluster = $job->clusterServer;

            if (! $cluster || $cluster->status !== 'active') {
                $this->warn("Skipping {$job->job_id}: cluster not active");

                continue;
            }

            try {
                $result = $factory->for($cluster)->pollJobStatus(
                    new PollJobStatusCommand($job, $cluster),
                );

                $canonical = $st->toCanonical($result->state);
                $data = $result->payload;

                $job->update(['last_poll_at' => now()]);

                $this->recordJobPolledAudit($job, $canonical);

                if (in_array($canonical, self::TERMINAL_STATES, true)) {
                    $this->dispatchSyntheticFinishedWebhook(
                        $webhookHandler,
                        $cluster,
                        $job,
                        $result->state,
                        $data,
                        $canonical,
                    );
                }

                $this->line("Polled {$job->job_id}: {$canonical}");
            } catch (UnknownStateException $e) {
                Log::channel('security')->warning("polling unknown state for {$job->job_id}: {$e->getMessage()}");
            } catch (\Throwable $e) {
                Log::channel('security')->warning("polling failed for {$job->job_id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatchSyntheticFinishedWebhook(
        WebhookHandler $webhookHandler,
        ClusterServer $cluster,
        Job $job,
        string $upstreamState,
        array $data,
        string $canonical,
    ): void {
        $webhookHandler->handle($cluster, [
            'event' => 'job.finished',
            'job_id' => $job->job_id,
            'state' => $upstreamState,
            'finished_at' => isset($data['finished_at']) && is_string($data['finished_at'])
                ? $data['finished_at']
                : now()->toIso8601String(),
            'exit_code' => isset($data['exit_code']) ? (int) $data['exit_code'] : ($canonical === 'success' ? 0 : 1),
            'from_polling' => true,
            'log_tail' => [],
        ]);
    }

    private function recordJobPolledAudit(Job $job, string $canonical): void
    {
        $auditPayload = ['from_polling' => true, 'canonical' => $canonical];
        if ($job->correlation_id !== null && $job->correlation_id !== '') {
            $auditPayload['correlation_id'] = $job->correlation_id;
        }

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'job_polled',
            'resource_type' => 'job',
            'resource_id' => $job->job_id,
            'payload' => $auditPayload,
            'cluster_server_id' => $job->cluster_server_id,
            'job_id' => $job->job_id,
        ]);
    }
}
