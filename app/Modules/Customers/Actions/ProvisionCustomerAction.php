<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProvisionCustomerAction
{
    public function __construct(
        private readonly SshClientInterface $ssh,
        private readonly JobTypeTranslator $translator,
    ) {}

    /**
     * @return array{customer: Customer, job: Job}
     *
     * @throws ClusterUnreachableException
     * @throws IdempotencyConflictException
     * @throws StateConflictException
     * @throws SshRemoteException
     */
    public function execute(ProvisionPayload $payload, Operator $actor): array
    {
        $cluster = ClusterServer::findOrFail($payload->clusterServerId);

        if ($cluster->status !== 'active') {
            throw new ClusterUnreachableException;
        }

        $idempotencyKey = (string) Str::uuid();
        $callbackUrl = config('app.url').'/api/jobs/hook?cluster='.$cluster->id;

        $args = [
            $payload->slug,
            $payload->domain,
            'create',
            "--idempotency-key={$idempotencyKey}",
            "--callback={$callbackUrl}",
        ];

        if ($payload->fullApps) {
            $args[] = '--full-apps';
        }

        if (! empty($payload->apps)) {
            $args[] = '--apps='.implode(',', $payload->apps);
        }

        // Annexes: > 256 KB per file → SFTP staging (Canal B); ≤ 256 KB → inline base64 via payload-stdin
        $stdin = [];
        $stagingId = null;
        $useSftp = ($payload->logoPath && filesize($payload->logoPath) > 256 * 1024)
            || ($payload->backgroundPath && filesize($payload->backgroundPath) > 256 * 1024);

        if ($payload->logoPath || $payload->backgroundPath) {
            if ($useSftp) {
                $stagingId = (string) Str::uuid();

                // Step 1 — init staging dir via Canal A
                $this->ssh->inboxInit($cluster, $stagingId);

                // Step 2 — upload files via Canal B (chroot-relative paths)
                if ($payload->logoPath) {
                    $this->ssh->sftpUpload(
                        $cluster,
                        $payload->logoPath,
                        $stagingId,
                        'logo.png'
                    );
                }
                if ($payload->backgroundPath) {
                    $this->ssh->sftpUpload(
                        $cluster,
                        $payload->backgroundPath,
                        $stagingId,
                        'background.jpg'
                    );
                }
                $args[] = "--staging-id={$stagingId}";
            } else {
                if ($payload->logoPath) {
                    $stdin['logo_data_url'] = 'data:image/png;base64,'.base64_encode((string) file_get_contents($payload->logoPath));
                }
                if ($payload->backgroundPath) {
                    $stdin['background_data_url'] = 'data:image/png;base64,'.base64_encode((string) file_get_contents($payload->backgroundPath));
                }
            }
        }

        // Persist idempotency key BEFORE SSH call to prevent duplicate submissions.
        // customer_slug is null at this point — the customer does not exist yet.
        DB::transaction(function () use ($idempotencyKey, $args): void {
            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'cmd' => 'create',
                'args_hash' => hash('sha256', json_encode($args)),
                'expires_at' => now()->addHours(24),
            ]);
        });

        try {
            // runAsync appends --async --json automatically
            $resp = $this->ssh->runAsync(
                $cluster,
                'nextcloud-manage',
                $args,
                $stdin ? json_encode($stdin) : null
            );
        } catch (SshRemoteException $e) {
            if ($e->idempotencyConflict) {
                $existingJobId = $e->parsedJson['existing_job_id'] ?? null;
                throw new IdempotencyConflictException($existingJobId);
            }
            if ($e->stateConflict) {
                throw new StateConflictException($e->parsedJson['diff'] ?? []);
            }
            throw $e;
        } catch (SshConnectionException) {
            throw new ClusterUnreachableException;
        }

        $jobId = $resp->parsedJson['job_id']
            ?? throw new \RuntimeException('SSH did not return job_id in response');

        return DB::transaction(function () use ($payload, $cluster, $jobId, $idempotencyKey, $actor): array {
            $customer = Customer::create([
                'slug' => $payload->slug,
                'cluster_server_id' => $cluster->id,
                'domain' => $payload->domain,
                'status' => 'provisioning',
                'last_sync_at' => now(),
            ]);

            $job = Job::create([
                'job_id' => $jobId,
                'customer_slug' => $payload->slug,
                'cluster_server_id' => $cluster->id,
                'cmd_canonical' => 'create',
                'job_type' => $this->translator->cmdToJobType('create'),
                'state' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'payload_sanitized' => [
                    'slug' => $payload->slug,
                    'domain' => $payload->domain,
                    'apps' => $payload->apps,
                    'full_apps' => $payload->fullApps,
                ],
                'queued_at' => now(),
            ]);

            // Update after Customer + Job exist to satisfy FK constraints
            IdempotencyKey::where('key', $idempotencyKey)->update([
                'job_id' => $jobId,
                'customer_slug' => $payload->slug,
            ]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actor->id,
                'action' => 'provision_initiated',
                'resource_type' => 'customer',
                'resource_id' => $payload->slug,
                'payload' => $job->payload_sanitized,
                'cluster_server_id' => $cluster->id,
                'job_id' => $jobId,
            ]);

            return ['customer' => $customer, 'job' => $job];
        });
    }
}
