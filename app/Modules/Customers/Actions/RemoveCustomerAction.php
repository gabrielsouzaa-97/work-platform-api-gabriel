<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\ConfirmationMismatchException;
use App\Modules\Customers\Exceptions\RemoveInProgressException;
use App\Modules\Customers\Exceptions\StateConflictException;
use App\Modules\Integration\Dto\RemoveTenantCommand;
use App\Modules\Integration\Exceptions\PortStateConflictException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class RemoveCustomerAction
{
    public function __construct(
        private readonly JobTypeTranslator $translator,
        private readonly PlatformPortFactory $platformPortFactory,
    ) {}

    /**
     * @throws ClusterUnreachableException
     * @throws ConfirmationMismatchException
     * @throws RemoveInProgressException
     * @throws StateConflictException
     * @throws UpstreamUnavailableException
     */
    public function execute(
        string $slug,
        string $confirmSlug,
        bool $backupFirst,
        Operator $actor,
    ): Job {
        $customer = Customer::findOrFail($slug);

        if ($customer->slug !== $confirmSlug) {
            throw new ConfirmationMismatchException;
        }

        if (in_array($customer->status, ['removing', 'removed'], true)) {
            throw new RemoveInProgressException;
        }

        $cluster = $customer->clusterServer;
        $idempotencyKey = (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        Log::withContext(['correlation_id' => $correlationId]);
        $callbackUrl = config('app.url').'/api/jobs/hook?cluster='.$cluster->id;

        $args = array_values(array_filter([
            $slug,
            '_',
            'remove',
            '--force',
            $backupFirst ? '--backup-first' : null,
            "--idempotency-key={$idempotencyKey}",
            "--callback={$callbackUrl}",
        ]));

        try {
            $jobRef = $this->platformPortFactory
                ->for($cluster)
                ->removeTenant(new RemoveTenantCommand($cluster, $args));
        } catch (ClusterUnreachableException) {
            throw new ClusterUnreachableException;
        } catch (PortStateConflictException $e) {
            throw new StateConflictException($e->diff);
        }

        $jobId = $jobRef->jobId;

        return DB::transaction(function () use ($customer, $cluster, $jobId, $idempotencyKey, $correlationId, $actor, $backupFirst): Job {
            $customer->update(['status' => 'removing']);

            $job = Job::create([
                'job_id' => $jobId,
                'customer_slug' => $customer->slug,
                'cluster_server_id' => $cluster->id,
                'cmd_canonical' => 'remove',
                'job_type' => $this->translator->cmdToJobType('remove'),
                'state' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => $correlationId,
                'payload_sanitized' => ['backup_first' => $backupFirst],
                'queued_at' => now(),
            ]);

            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'cmd' => 'remove',
                'args_hash' => hash('sha256', $customer->slug.($backupFirst ? '|backup' : '')),
                'customer_slug' => $customer->slug,
                'job_id' => $jobId,
                'expires_at' => now()->addHours(24),
            ]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actor->id,
                'action' => 'remove_initiated',
                'resource_type' => 'customer',
                'resource_id' => $customer->slug,
                'payload' => [
                    'backup_first' => $backupFirst,
                    'severity' => 'high',
                    'correlation_id' => $correlationId,
                ],
                'cluster_server_id' => $cluster->id,
                'job_id' => $jobId,
            ]);

            return $job;
        });
    }
}
