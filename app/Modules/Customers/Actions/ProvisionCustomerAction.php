<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentTransportResolver;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ProvisionCustomerAction
{
    private const MAX_PAYLOAD_STDIN_BYTES = 262144;

    public function __construct(
        private readonly SshClientInterface $ssh,
        private readonly JobTypeTranslator $translator,
        private readonly AgentTransportResolver $transportResolver,
        private readonly AgentUpstreamGateway $agentGateway,
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

        // Annexes: payloads that exceed SSH stdin cap go through SFTP staging (Canal B).
        $stdin = [];
        $stagingId = null;

        try {
            if ($payload->logoPath || $payload->backgroundPath) {
                $inlineStdin = ['branding' => $this->brandingPayloadFor($payload)];

                if ($this->requiresSftp($payload, $inlineStdin)) {
                    $stagingId = (string) Str::uuid();

                    // Step 1 — init staging dir via Canal A
                    $this->ssh->inboxInit($cluster, $stagingId);

                    // Step 2 — upload files via Canal B (chroot-relative paths)
                    if ($payload->logoPath) {
                        $ext = $this->imageMimeFor($payload->logoPath) === 'image/jpeg' ? 'jpg' : 'png';
                        $this->ssh->sftpUpload(
                            $cluster,
                            $payload->logoPath,
                            $stagingId,
                            "logo.{$ext}"
                        );
                    }
                    if ($payload->backgroundPath) {
                        $ext = $this->imageMimeFor($payload->backgroundPath) === 'image/jpeg' ? 'jpg' : 'png';
                        $this->ssh->sftpUpload(
                            $cluster,
                            $payload->backgroundPath,
                            $stagingId,
                            "background.{$ext}"
                        );
                    }
                    $args[] = "--staging-id={$stagingId}";
                } else {
                    $stdin = $inlineStdin;
                    $args[] = '--payload-stdin';
                }
            }
        } catch (SshConnectionException) {
            throw new ClusterUnreachableException;
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

        $stdinJson = $stdin ? json_encode($stdin) : null;

        Log::debug('provision.ssh_dispatch', [
            'slug' => $payload->slug,
            'has_logo' => isset($stdin['branding']['logo_data_url']),
            'has_background' => isset($stdin['branding']['background_data_url']),
            'stdin_bytes' => $stdinJson !== null ? strlen($stdinJson) : 0,
            'has_payload_stdin_flag' => in_array('--payload-stdin', $args, true),
            'has_staging_id' => $stagingId !== null,
            'staging_id' => $stagingId,
            'logo_path' => $payload->logoPath,
            'logo_filesize' => $payload->logoPath && file_exists($payload->logoPath) ? filesize($payload->logoPath) : null,
        ]);

        $useAgentTransport = $this->transportResolver->shouldUseAgentTransport($cluster)
            && $stagingId === null;

        try {
            if ($useAgentTransport) {
                $resp = $this->agentGateway->runAsync(
                    $cluster,
                    'nextcloud-manage',
                    $args,
                    $stdinJson,
                );
            } else {
                // runAsync appends --async --json automatically
                $resp = $this->ssh->runAsync(
                    $cluster,
                    'nextcloud-manage',
                    $args,
                    $stdinJson
                );
            }
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
            // If a ghost (soft-deleted) Customer exists from a previous failed provisioning,
            // restore it and update its fields instead of forceDelete + re-create.
            // forceDelete would violate jobs.customer_slug FK RESTRICT (jobs from the
            // previous attempt are preserved as audit trail).
            $ghost = Customer::withTrashed()
                ->where('slug', $payload->slug)
                ->whereNotNull('deleted_at')
                ->first();

            if ($ghost) {
                $ghost->restore();
                $ghost->update([
                    'cluster_server_id' => $cluster->id,
                    'domain' => $payload->domain,
                    'status' => 'provisioning',
                    'last_sync_at' => now(),
                ]);
                $customer = $ghost;
            } else {
                $customer = Customer::create([
                    'slug' => $payload->slug,
                    'cluster_server_id' => $cluster->id,
                    'domain' => $payload->domain,
                    'status' => 'provisioning',
                    'last_sync_at' => now(),
                ]);
            }

            $this->persistBrandingFiles($customer, $payload);

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

    private function dataUrlFor(string $path): string
    {
        return 'data:'.$this->imageMimeFor($path).';base64,'.base64_encode((string) file_get_contents($path));
    }

    private function brandingPayloadFor(ProvisionPayload $payload): array
    {
        $branding = [];

        if ($payload->logoPath) {
            $branding['logo_data_url'] = $this->dataUrlFor($payload->logoPath);
        }

        if ($payload->backgroundPath) {
            $branding['background_data_url'] = $this->dataUrlFor($payload->backgroundPath);
        }

        return $branding;
    }

    private function requiresSftp(ProvisionPayload $payload, array $stdin): bool
    {
        $hasLargeFile = ($payload->logoPath && filesize($payload->logoPath) > 256 * 1024)
            || ($payload->backgroundPath && filesize($payload->backgroundPath) > 256 * 1024);

        return $hasLargeFile || strlen((string) json_encode($stdin)) > self::MAX_PAYLOAD_STDIN_BYTES;
    }

    private function imageMimeFor(string $path): string
    {
        $mime = @mime_content_type($path);

        return in_array($mime, ['image/png', 'image/jpeg'], true)
            ? $mime
            : 'image/png';
    }

    private function persistBrandingFiles(Customer $customer, ProvisionPayload $payload): void
    {
        $brandingMeta = $customer->branding_meta ?? [];

        if ($payload->logoPath) {
            $brandingMeta['logo_path'] = $this->storeBrandingFile($payload->slug, $payload->logoPath, 'logo');
        }

        if ($payload->backgroundPath) {
            $brandingMeta['background_path'] = $this->storeBrandingFile(
                $payload->slug,
                $payload->backgroundPath,
                'background'
            );
        }

        if ($brandingMeta !== ($customer->branding_meta ?? [])) {
            $customer->update(['branding_meta' => $brandingMeta]);
        }
    }

    private function storeBrandingFile(string $slug, string $sourcePath, string $name): string
    {
        $extension = $this->imageMimeFor($sourcePath) === 'image/jpeg' ? 'jpg' : 'png';
        $relativePath = "branding/{$slug}/{$name}.{$extension}";

        if (! Storage::disk('local')->put($relativePath, (string) file_get_contents($sourcePath))) {
            throw new \RuntimeException("Unable to persist branding file [{$relativePath}]");
        }

        return $relativePath;
    }
}
