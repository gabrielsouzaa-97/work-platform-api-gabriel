<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Job;
use App\Modules\Core\Translators\Exceptions\UnknownStateException;
use App\Modules\Core\Translators\StateTranslator;
use App\Modules\Integration\Dto\PollJobStatusCommand;
use App\Modules\Integration\Services\PlatformPortFactory;
use App\Modules\Jobs\Services\TransportObservability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobsPollStuckCommand extends Command
{
    protected $signature = 'jobs:poll-stuck';

    protected $description = 'Poll upstream for jobs stuck in running state with no callback after 60s';

    public function handle(
        PlatformPortFactory $factory,
        StateTranslator $st,
        TransportObservability $observability,
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
                $job->update([
                    'state' => $canonical,
                    'last_poll_at' => now(),
                    'finished_at' => isset($data['finished_at']) ? $data['finished_at'] : $job->finished_at,
                    'exit_code' => $data['exit_code'] ?? null,
                ]);

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

                $this->line("Polled {$job->job_id}: {$canonical}");
            } catch (UnknownStateException $e) {
                Log::channel('security')->warning("polling unknown state for {$job->job_id}: {$e->getMessage()}");
            } catch (\Throwable $e) {
                Log::channel('security')->warning("polling failed for {$job->job_id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
