<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Services;

use App\Models\Job;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class TransportObservability
{
    public const TRANSPORT_SSH = 'ssh';

    public const TRANSPORT_AGENT = 'agent';

    private const TERMINAL_STATES = ['success', 'failed', 'cancelled'];

    public function isEnabled(): bool
    {
        return (bool) config('observability.enabled', true);
    }

    public function stuckJobSlaSeconds(): int
    {
        return (int) config('observability.stuck_job_sla_seconds', 60);
    }

    public function missingWebhookSlaSeconds(): int
    {
        return (int) config('observability.missing_webhook_sla_seconds', 60);
    }

    public function recordDispatch(string $transport, string $jobId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        Cache::put(
            $this->dispatchCacheKey($jobId),
            $transport,
            (int) config('observability.dispatch_cache_ttl_seconds', 86400),
        );

        Log::info('transport.dispatch', [
            'job_id' => $jobId,
            'transport' => $transport,
        ]);
    }

    public function attachTransportToJob(Job $job): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $transport = Cache::get($this->dispatchCacheKey($job->job_id));
        if (! is_string($transport) || $transport === '') {
            return;
        }

        $payload = $job->payload_sanitized ?? [];
        if (($payload['transport'] ?? null) === $transport) {
            return;
        }

        $payload['transport'] = $transport;
        $job->update(['payload_sanitized' => $payload]);
    }

    public function alertJobStuckBeyondSla(Job $job): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $sla = $this->stuckJobSlaSeconds();
        $elapsed = $this->elapsedSinceQueued($job);
        if ($elapsed < $sla) {
            return;
        }

        Log::warning('transport.job_stuck_sla', $this->baseContext($job, [
            'elapsed_seconds' => $elapsed,
            'sla_seconds' => $sla,
        ]));
    }

    public function alertMissingWebhook(Job $job): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if ($job->callback_received_at !== null) {
            return;
        }

        $sla = $this->missingWebhookSlaSeconds();
        $elapsed = $this->elapsedSinceQueued($job);
        if ($elapsed < $sla) {
            return;
        }

        Log::warning('transport.webhook_missing', $this->baseContext($job, [
            'elapsed_seconds' => $elapsed,
            'sla_seconds' => $sla,
        ]));
    }

    public function runScheduledChecks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->scanMissingWebhooks();
        $this->checkTransportParity();
    }

    private function scanMissingWebhooks(): void
    {
        $threshold = now()->subSeconds($this->missingWebhookSlaSeconds());

        Job::query()
            ->whereNull('callback_received_at')
            ->whereNotIn('state', self::TERMINAL_STATES)
            ->where('queued_at', '<', $threshold)
            ->limit(100)
            ->each(fn (Job $job) => $this->alertMissingWebhook($job));
    }

    private function checkTransportParity(): void
    {
        if (! (bool) config('observability.parity.enabled', true)) {
            return;
        }

        $lookbackHours = (int) config('observability.parity.lookback_hours', 24);
        $jobs = Job::query()
            ->whereIn('state', self::TERMINAL_STATES)
            ->where('finished_at', '>=', now()->subHours($lookbackHours))
            ->limit(500)
            ->get();

        foreach ($jobs->groupBy(fn (Job $job) => $job->job_type) as $jobType => $typeJobs) {
            $this->compareParityForJobType((string) $jobType, $typeJobs);
        }
    }

    /**
     * @param  Collection<int, Job>  $jobs
     */
    private function compareParityForJobType(string $jobType, Collection $jobs): void
    {
        $byTransport = $jobs->groupBy(fn (Job $job) => $this->transportFor($job));
        $ssh = $byTransport->get(self::TRANSPORT_SSH, collect());
        $agent = $byTransport->get(self::TRANSPORT_AGENT, collect());
        $minSamples = (int) config('observability.parity.min_samples_per_transport', 5);

        if ($ssh->count() < $minSamples || $agent->count() < $minSamples) {
            return;
        }

        $sshRate = $this->successRate($ssh);
        $agentRate = $this->successRate($agent);
        $delta = abs($sshRate - $agentRate);
        $threshold = (float) config('observability.parity.success_rate_delta_threshold', 0.15);

        if ($delta < $threshold) {
            return;
        }

        Log::warning('transport.parity_divergence', [
            'job_type' => $jobType,
            'ssh_success_rate' => $sshRate,
            'agent_success_rate' => $agentRate,
            'delta' => $delta,
            'threshold' => $threshold,
            'ssh_samples' => $ssh->count(),
            'agent_samples' => $agent->count(),
        ]);
    }

    /**
     * @param  Collection<int, Job>  $jobs
     */
    private function successRate(Collection $jobs): float
    {
        if ($jobs->isEmpty()) {
            return 0.0;
        }

        return $jobs->where('state', 'success')->count() / $jobs->count();
    }

    private function transportFor(Job $job): string
    {
        $fromPayload = $job->payload_sanitized['transport'] ?? null;
        if (is_string($fromPayload) && $fromPayload !== '') {
            return $fromPayload;
        }

        $cached = Cache::get($this->dispatchCacheKey($job->job_id));

        return is_string($cached) && $cached !== '' ? $cached : 'unknown';
    }

    private function elapsedSinceQueued(Job $job): int
    {
        if ($job->queued_at === null) {
            return 0;
        }

        return (int) $job->queued_at->diffInSeconds(now());
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function baseContext(Job $job, array $extra = []): array
    {
        return array_merge([
            'job_id' => $job->job_id,
            'job_type' => $job->job_type,
            'state' => $job->state,
            'transport' => $this->transportFor($job),
            'correlation_id' => $job->correlation_id,
            'cluster_server_id' => $job->cluster_server_id,
        ], $extra);
    }

    private function dispatchCacheKey(string $jobId): string
    {
        return 'transport_obs:dispatch:'.$jobId;
    }
}
