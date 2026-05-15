<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Customers\Exceptions\ConfirmationMismatchException;
use App\Modules\Customers\Exceptions\RemoveInProgressException;
use App\Modules\Customers\Exceptions\StateConflictException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RemoveCustomerAction
{
    public function __construct(
        private readonly SshClientInterface $ssh,
        private readonly JobTypeTranslator $translator,
    ) {}

    /**
     * @throws ConfirmationMismatchException
     * @throws RemoveInProgressException
     * @throws StateConflictException
     * @throws SshRemoteException
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
        $callbackUrl = config('app.url').'/api/jobs/hook?cluster='.$cluster->id;

        // [E1,E4] nextcloud-manage <client> _ remove --force [--backup-first] --async --json
        // runAsync appends --async --json automatically
        $args = array_filter([
            $slug,
            '_',
            'remove',
            '--force',
            $backupFirst ? '--backup-first' : null,
            "--idempotency-key={$idempotencyKey}",
            "--callback={$callbackUrl}",
        ]);

        try {
            $resp = $this->ssh->runAsync($cluster, 'nextcloud-manage', array_values($args));
        } catch (SshRemoteException $e) {
            if ($e->stateConflict) {
                throw new StateConflictException($e->parsedJson['diff'] ?? []);
            }
            throw $e;
        }

        $jobId = $resp->parsedJson['job_id']
            ?? throw new \RuntimeException('SSH did not return job_id in response');

        return DB::transaction(function () use ($customer, $cluster, $jobId, $idempotencyKey, $actor, $backupFirst): Job {
            $customer->update(['status' => 'removing']);

            $job = Job::create([
                'job_id' => $jobId,
                'customer_slug' => $customer->slug,
                'cluster_server_id' => $cluster->id,
                'cmd_canonical' => 'remove',
                'job_type' => $this->translator->cmdToJobType('remove'),
                'state' => 'queued',
                'idempotency_key' => $idempotencyKey,
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
                'payload' => ['backup_first' => $backupFirst, 'severity' => 'high'],
                'cluster_server_id' => $cluster->id,
                'job_id' => $jobId,
            ]);

            return $job;
        });
    }
}
