<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Core\Translators\Exceptions\BlockedOnUpstreamException;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LifecycleAsyncAction
{
    public function __construct(
        private readonly SshClientInterface $ssh,
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
     * @throws BlockedOnUpstreamException When the upstream verb is not yet implemented
     *                                    (currently: groups:add / groups:remove).
     * @throws ClusterUnreachableException
     * @throws IdempotencyConflictException
     * @throws SshRemoteException
     */
    public function execute(
        Customer $customer,
        string $cmd,
        array $args,
        ?array $stdinPayload,
        Operator $actor,
    ): Job {
        // Translate canonical cmd (e.g. `users:create`) into upstream argv tokens
        // (e.g. `user`, `create`). Done FIRST so blocked-on-upstream cmds short-circuit
        // before touching DB/SSH — keeps idempotency table clean.
        $upstreamVerbTokens = $this->translator->cmdToCliArgv($cmd);

        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            throw new ClusterUnreachableException;
        }

        $argsHash = hash('sha256', $customer->slug.'|'.$cmd.'|'.json_encode($args));

        $existing = IdempotencyKey::where('args_hash', $argsHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            throw new IdempotencyConflictException($existing->job_id);
        }

        $idempotencyKey = (string) Str::uuid();
        $callbackUrl = config('app.url').'/api/jobs/hook?cluster='.$cluster->id;

        // `--async --json` are intentionally NOT appended here — SshClient::runAsync()
        // appends them. Duplicating the flags was Bug B of ISSUE-006.
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

        // Persist key BEFORE SSH to prevent duplicate submissions on retry.
        DB::transaction(function () use ($idempotencyKey, $argsHash, $cmd, $customer): void {
            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'cmd' => $cmd,
                'args_hash' => $argsHash,
                'customer_slug' => $customer->slug,
                'expires_at' => now()->addHours(24),
            ]);
        });

        try {
            $resp = $this->ssh->runAsync(
                $cluster,
                'nextcloud-manage',
                $sshArgs,
                $stdinPayload !== null ? json_encode($stdinPayload) : null,
            );
        } catch (SshConnectionException) {
            IdempotencyKey::where('key', $idempotencyKey)->delete();
            throw new ClusterUnreachableException;
        } catch (SshTimeoutException $e) {
            IdempotencyKey::where('key', $idempotencyKey)->delete();
            throw $e;
        } catch (SshRemoteException $e) {
            IdempotencyKey::where('key', $idempotencyKey)->delete();
            throw $e;
        }

        $jobId = $resp->parsedJson['job_id']
            ?? throw new \RuntimeException('SSH did not return job_id in async response');

        return DB::transaction(function () use ($customer, $cluster, $jobId, $idempotencyKey, $cmd, $args, $actor): Job {
            $job = Job::create([
                'job_id' => $jobId,
                'customer_slug' => $customer->slug,
                'cluster_server_id' => $cluster->id,
                'cmd_canonical' => $cmd,
                'job_type' => $this->translator->cmdToJobType($cmd),
                'state' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'payload_sanitized' => ['cmd' => $cmd, 'args' => $args], // no stdin (may contain passwords)
                'queued_at' => now(),
            ]);

            IdempotencyKey::where('key', $idempotencyKey)->update(['job_id' => $jobId]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actor->id,
                'action' => str_replace(':', '_', $cmd).'_initiated',
                'resource_type' => 'customer',
                'resource_id' => $customer->slug,
                'payload' => ['cmd' => $cmd, 'args' => $args],
                'cluster_server_id' => $cluster->id,
                'job_id' => $jobId,
            ]);

            return $job;
        });
    }
}
