<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Job;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Core\Translators\Exceptions\UnknownStateException;
use App\Modules\Core\Translators\StateTranslator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobsPollStuckCommand extends Command
{
    protected $signature = 'jobs:poll-stuck';

    protected $description = 'Poll upstream for jobs stuck in running state with no callback after 60s';

    public function handle(SshClientInterface $ssh, StateTranslator $st): int
    {
        $stuck = Job::query()
            ->where('state', 'running')
            ->whereNull('callback_received_at')
            ->where('queued_at', '<', now()->subMinute())
            ->limit(50)
            ->with('clusterServer')
            ->get();

        foreach ($stuck as $job) {
            $cluster = $job->clusterServer;

            if (! $cluster || $cluster->status !== 'active') {
                $this->warn("Skipping {$job->job_id}: cluster not active");

                continue;
            }

            try {
                $resp = $ssh->run(
                    $cluster,
                    'nextcloud-manage',
                    ['job', $job->job_id, 'status', '--json'],
                    null,
                    30
                );

                $data = $resp->parsedJson;
                if (! $data || ! isset($data['state'])) {
                    $this->warn("No valid JSON response for {$job->job_id}");

                    continue;
                }

                $canonical = $st->toCanonical($data['state']);
                $job->update([
                    'state' => $canonical,
                    'last_poll_at' => now(),
                    'finished_at' => isset($data['finished_at']) ? $data['finished_at'] : $job->finished_at,
                    'exit_code' => $data['exit_code'] ?? null,
                ]);

                AuditLog::create([
                    'id' => Str::uuid()->toString(),
                    'actor_id' => null,
                    'action' => 'job_polled',
                    'resource_type' => 'job',
                    'resource_id' => $job->job_id,
                    'payload' => ['from_polling' => true, 'canonical' => $canonical],
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
