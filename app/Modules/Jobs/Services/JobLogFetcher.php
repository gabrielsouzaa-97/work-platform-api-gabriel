<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Services;

use App\Models\ClusterServer;
use App\Models\Job;
use App\Modules\Integration\Dto\FetchJobLogsCommand;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Integration\Services\PlatformPortFactory;
use App\Modules\Jobs\Exceptions\JobLogFetchException;

/**
 * Fetches job log lines from the upstream nextcloud-saas-manager via PlatformPort.
 */
final class JobLogFetcher
{
    public function __construct(
        private readonly PlatformPortFactory $factory,
    ) {}

    /**
     * @return array<int, string> Sanitised log lines, or [] if no lines available or idempotent.
     *
     * @throws JobLogFetchException on upstream/parse failure (caller handles gracefully).
     */
    public function fetch(Job $job, ClusterServer $cluster): array
    {
        if (! empty($job->summary)) {
            return [];
        }

        try {
            return $this->factory->for($cluster)
                ->fetchJobLogs(new FetchJobLogsCommand($job, $cluster))
                ->lines;
        } catch (UpstreamUnavailableException $e) {
            throw new JobLogFetchException(
                "Upstream failed fetching logs for job {$job->job_id}: {$e->getMessage()}",
                previous: $e,
            );
        } catch (\RuntimeException $e) {
            throw new JobLogFetchException($e->getMessage(), previous: $e);
        }
    }
}
