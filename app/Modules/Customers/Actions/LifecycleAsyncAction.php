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
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\TenantNotReadyException;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use App\Modules\Integration\Dto\AsyncJobRef;
use App\Modules\Integration\Dto\ManageAsyncCommand;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class LifecycleAsyncAction
{
    /** @var list<string> */
    private const USER_READINESS_GATED_CMDS = ['users:create', 'users:delete'];

    public function __construct(
        private readonly PlatformPortFactory $platformPortFactory,
        private readonly JobTypeTranslator $translator,
    ) {}

    /**
     * Dispatch an async lifecycle operation (users/groups/apps) to the upstream.
     *
     * @param  string  $cmd  One of: users:create, users:delete, groups:create, groups:delete,
     *                       groups:add, groups:remove, apps:enable, apps:disable.
     * @param  array<int, string>  $args  Positional args (no credentials — those go via stdin).
     * @param  array<string, mixed>|null  $stdinPayload  Sensitive payload (e.g. password).
     *
     * @throws ClusterUnreachableException
     * @throws IdempotencyConflictException
     * @throws SshRemoteException
     * @throws TenantNotReadyException
     */
    public function execute(
        Customer $customer,
        string $cmd,
        array $args,
        ?array $stdinPayload,
        Operator $actor,
    ): Job {
        $upstreamVerbTokens = $this->translator->cmdToCliArgv($cmd);
        $cluster = $this->resolveActiveCluster($customer);
        $this->assertTenantReadyForUserOps($customer, $cmd);

        $argsHash = $this->buildArgsHash($customer, $cmd, $args);
        $this->assertNoIdempotencyConflict($argsHash);

        $idempotencyKey = (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        Log::withContext(['correlation_id' => $correlationId]);
        $callbackUrl = config('app.url').'/api/jobs/hook?cluster='.$cluster->id;
        $sshArgs = $this->buildSshArgs(
            $customer,
            $upstreamVerbTokens,
            $args,
            $idempotencyKey,
            $callbackUrl,
            $stdinPayload,
        );

        $this->persistIdempotencyKey($idempotencyKey, $argsHash, $cmd, $customer);

        $resp = $this->dispatchViaPort($cluster, $sshArgs, $stdinPayload, $idempotencyKey);

        $jobId = $resp->jobId;

        return $this->persistJobAndAudit(
            $customer,
            $cluster,
            $jobId,
            $idempotencyKey,
            $correlationId,
            $cmd,
            $args,
            $actor,
        );
    }

    private function resolveActiveCluster(Customer $customer): ClusterServer
    {
        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            throw new ClusterUnreachableException;
        }

        return $cluster;
    }

    private function assertTenantReadyForUserOps(Customer $customer, string $cmd): void
    {
        if (
            in_array($cmd, self::USER_READINESS_GATED_CMDS, true)
            && in_array($customer->status, CustomerLifecycleStatus::USER_OPS_BLOCKED, true)
        ) {
            throw new TenantNotReadyException($customer->status);
        }
    }

    /**
     * @param  array<int, string>  $args
     */
    private function buildArgsHash(Customer $customer, string $cmd, array $args): string
    {
        return hash('sha256', $customer->slug.'|'.$cmd.'|'.json_encode($args));
    }

    private function assertNoIdempotencyConflict(string $argsHash): void
    {
        $existing = IdempotencyKey::where('args_hash', $argsHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            throw new IdempotencyConflictException($existing->job_id);
        }
    }

    /**
     * @param  list<string>  $upstreamVerbTokens
     * @param  array<int, string>  $args
     * @return list<string>
     */
    private function buildSshArgs(
        Customer $customer,
        array $upstreamVerbTokens,
        array $args,
        string $idempotencyKey,
        string $callbackUrl,
        ?array $stdinPayload,
    ): array {
        $sshArgs = array_merge(
            [$customer->slug, ...$upstreamVerbTokens],
            $args,
            [
                "--idempotency-key={$idempotencyKey}",
                "--callback={$callbackUrl}",
            ],
        );

        if ($stdinPayload !== null) {
            $sshArgs[] = '--payload-stdin';
        }

        return $sshArgs;
    }

    private function persistIdempotencyKey(
        string $idempotencyKey,
        string $argsHash,
        string $cmd,
        Customer $customer,
    ): void {
        DB::transaction(function () use ($idempotencyKey, $argsHash, $cmd, $customer): void {
            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'cmd' => $cmd,
                'args_hash' => $argsHash,
                'customer_slug' => $customer->slug,
                'expires_at' => now()->addHours(24),
            ]);
        });
    }

    /**
     * @param  list<string>  $sshArgs
     */
    private function dispatchViaPort(
        ClusterServer $cluster,
        array $sshArgs,
        ?array $stdinPayload,
        string $idempotencyKey,
    ): AsyncJobRef {
        $stdinJson = $stdinPayload !== null ? json_encode($stdinPayload) : null;

        try {
            return $this->platformPortFactory->for($cluster)->dispatchManageAsync(
                new ManageAsyncCommand($cluster, $sshArgs, $stdinJson),
            );
        } catch (ClusterUnreachableException|SshConnectionException) {
            IdempotencyKey::where('key', $idempotencyKey)->delete();
            throw new ClusterUnreachableException;
        } catch (SshTimeoutException $e) {
            IdempotencyKey::where('key', $idempotencyKey)->delete();
            throw $e;
        } catch (SshRemoteException $e) {
            IdempotencyKey::where('key', $idempotencyKey)->delete();
            throw $e;
        }
    }

    /**
     * @param  array<int, string>  $args
     */
    private function persistJobAndAudit(
        Customer $customer,
        ClusterServer $cluster,
        string $jobId,
        string $idempotencyKey,
        string $correlationId,
        string $cmd,
        array $args,
        Operator $actor,
    ): Job {
        return DB::transaction(function () use (
            $customer,
            $cluster,
            $jobId,
            $idempotencyKey,
            $correlationId,
            $cmd,
            $args,
            $actor,
        ): Job {
            $job = Job::create([
                'job_id' => $jobId,
                'customer_slug' => $customer->slug,
                'cluster_server_id' => $cluster->id,
                'cmd_canonical' => $cmd,
                'job_type' => $this->translator->cmdToJobType($cmd),
                'state' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => $correlationId,
                'payload_sanitized' => ['cmd' => $cmd, 'args' => $args],
                'queued_at' => now(),
            ]);

            IdempotencyKey::where('key', $idempotencyKey)->update(['job_id' => $jobId]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actor->id,
                'action' => str_replace(':', '_', $cmd).'_initiated',
                'resource_type' => 'customer',
                'resource_id' => $customer->slug,
                'payload' => [
                    'cmd' => $cmd,
                    'args' => $args,
                    'correlation_id' => $correlationId,
                ],
                'cluster_server_id' => $cluster->id,
                'job_id' => $jobId,
            ]);

            return $job;
        });
    }
}
