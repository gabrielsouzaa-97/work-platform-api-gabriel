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
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use App\Modules\Integration\Dto\ManageAsyncCommand;
use App\Modules\Integration\Exceptions\PortIdempotencyConflictException;
use App\Modules\Integration\Exceptions\PortStateConflictException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TenantSuspendAction
{
    public function __construct(
        private readonly PlatformPortFactory $platformPortFactory,
        private readonly JobTypeTranslator $translator,
    ) {}

    /**
     * @throws ClusterUnreachableException
     * @throws IdempotencyConflictException
     * @throws StateConflictException
     * @throws UpstreamUnavailableException
     */
    public function execute(Customer $customer, Operator $actor): Job
    {
        return $this->dispatchLifecycle($customer, 'stop', 'suspended', 'tenant_suspend_initiated', $actor);
    }

    /**
     * @throws ClusterUnreachableException
     * @throws IdempotencyConflictException
     * @throws StateConflictException
     * @throws UpstreamUnavailableException
     */
    private function dispatchLifecycle(
        Customer $customer,
        string $cmd,
        string $targetStatus,
        string $auditAction,
        Operator $actor,
    ): Job {
        $cluster = $customer->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            throw new ClusterUnreachableException;
        }

        $idempotencyKey = (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        Log::withContext(['correlation_id' => $correlationId]);
        $callbackUrl = config('app.url').'/api/jobs/hook?cluster='.$cluster->id;

        $args = [
            $customer->slug,
            '_',
            $cmd,
            "--idempotency-key={$idempotencyKey}",
            "--callback={$callbackUrl}",
        ];

        try {
            $jobRef = $this->platformPortFactory->for($cluster)->dispatchManageAsync(
                new ManageAsyncCommand($cluster, $args, null),
            );
        } catch (PortIdempotencyConflictException $e) {
            throw new IdempotencyConflictException($e->existingJobId);
        } catch (PortStateConflictException $e) {
            throw new StateConflictException($e->diff);
        }

        return DB::transaction(function () use (
            $customer,
            $cluster,
            $jobRef,
            $idempotencyKey,
            $correlationId,
            $cmd,
            $targetStatus,
            $auditAction,
            $actor,
        ): Job {
            $customer->update(['status' => $targetStatus]);

            $job = Job::create([
                'job_id' => $jobRef->jobId,
                'customer_slug' => $customer->slug,
                'cluster_server_id' => $cluster->id,
                'cmd_canonical' => $cmd,
                'job_type' => $this->translator->cmdToJobType($cmd),
                'state' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => $correlationId,
                'payload_sanitized' => ['cmd' => $cmd],
                'queued_at' => now(),
            ]);

            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'cmd' => $cmd,
                'args_hash' => hash('sha256', $customer->slug.'|'.$cmd),
                'customer_slug' => $customer->slug,
                'job_id' => $jobRef->jobId,
                'expires_at' => now()->addHours(24),
            ]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actor->id,
                'action' => $auditAction,
                'resource_type' => 'customer',
                'resource_id' => $customer->slug,
                'payload' => ['correlation_id' => $correlationId],
                'cluster_server_id' => $cluster->id,
                'job_id' => $jobRef->jobId,
            ]);

            return $job;
        });
    }
}
