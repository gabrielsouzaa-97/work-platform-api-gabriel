<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Jobs\Services\JobLogFetcher;
use Illuminate\Support\Str;

function jobLogsContractSkipUnlessEnabled(): void
{
    if (env('RUN_UPSTREAM_CONTRACT') !== '1' && env('RUN_UPSTREAM_CONTRACT') !== 1 && env('RUN_UPSTREAM_CONTRACT') !== true) {
        test()->markTestSkipped('Opt-in only. Set RUN_UPSTREAM_CONTRACT=1 to run against homolog cluster.');
    }
}

/**
 * Contract test — opt-in via RUN_UPSTREAM_CONTRACT=1.
 *
 * Validates that the upstream nextcloud-saas-manager responds to
 * `nextcloud-manage <client> job <job_id> logs --json` (primary) or
 * `nextcloud-manage <client> job <job_id> status --json` (fallback, exit_code=99)
 * with a parseable array of strings.
 *
 * Results must be documented in docs/.briefs/F6.brief.md.
 *
 * Usage:
 *   RUN_UPSTREAM_CONTRACT=1 php artisan test tests/Contract/Jobs/UpstreamJobLogsContractTest.php
 */
test('nextcloud-manage job logs --json (ou status fallback) retorna array de strings parseável', function (): void {
    jobLogsContractSkipUnlessEnabled();

    $clusterServerId = env('CONTRACT_CLUSTER_SERVER_ID');

    if (! $clusterServerId) {
        $this->markTestSkipped(
            'CONTRACT_CLUSTER_SERVER_ID não definido. '
            .'Defina no .env.testing com o UUID do cluster homolog.'
        );
    }

    $cluster = ClusterServer::find($clusterServerId);

    if (! $cluster) {
        $this->markTestSkipped("ClusterServer {$clusterServerId} não encontrado no banco de dados.");
    }

    $contractJobId = env('CONTRACT_JOB_ID');

    if (! $contractJobId) {
        $this->markTestSkipped(
            'CONTRACT_JOB_ID não definido. '
            .'Defina no .env.testing com um job_id conhecido no cluster homolog.'
        );
    }

    $customerSlug = env('CONTRACT_CUSTOMER_SLUG', 'homolog-contract');

    Customer::firstOrCreate(['slug' => $customerSlug], [
        'cluster_server_id' => $cluster->id,
        'domain' => "{$customerSlug}.example.com",
        'status' => 'active',
    ]);

    $job = Job::firstOrCreate(['job_id' => $contractJobId], [
        'customer_slug' => $customerSlug,
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => "nextcloud-manage {$customerSlug} _ provision",
        'job_type' => 'provision',
        'state' => 'success',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subHour(),
    ]);

    $fetcher = app(JobLogFetcher::class);
    $lines = $fetcher->fetch($job, $cluster);

    expect($lines)->toBeArray();

    foreach ($lines as $line) {
        expect($line)->toBeString();
    }
});
