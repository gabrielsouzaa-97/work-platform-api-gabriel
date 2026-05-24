<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\Customer;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;

final class OccPassthroughService
{
    public function __construct(private readonly SshClientInterface $ssh) {}

    /**
     * Execute an OCC subcommand synchronously via `nextcloud-manage <client> occ-exec <subcmd>`.
     *
     * [E1,E8] No domain positional argument — slug directly followed by occ-exec.
     *
     * @param  array<int, string>  $args  Extra positional/flag arguments for the OCC subcommand.
     * @return array<string, mixed>
     *
     * @throws ClusterUnreachableException
     * @throws SshTimeoutException
     * @throws SshRemoteException
     */
    public function exec(Customer $customer, string $subcmd, array $args = [], int $timeoutSec = 60): array
    {
        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            throw new ClusterUnreachableException;
        }

        $sshArgs = array_merge(
            [$customer->slug, 'occ-exec', $subcmd],
            $args,
            ['--json'],
        );

        try {
            $resp = $this->ssh->run($cluster, 'nextcloud-manage', $sshArgs, null, $timeoutSec);
        } catch (SshConnectionException) {
            throw new ClusterUnreachableException;
        }

        return $resp->parsedJson
            ?? throw new \RuntimeException("OCC '{$subcmd}' did not return valid JSON (stdout: {$resp->stdout})");
    }

    /**
     * Apply theming keys one at a time — OCC `theming:config` accepts only `<key> <value>` per invocation (P-10).
     *
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    public function execThemingConfig(Customer $customer, array $fields, int $timeoutSec = 60): array
    {
        if ($fields === []) {
            return $this->exec($customer, 'theming:config', [], $timeoutSec);
        }

        $last = [];
        foreach ($fields as $key => $value) {
            $last = $this->exec($customer, 'theming:config', [$key, $value], $timeoutSec);
        }

        return $last;
    }

    /** Pre-defined quota options — no SSH call needed. */
    public static function quotaOptions(): array
    {
        return [
            '512 MB', '1 GB', '2 GB', '5 GB', '10 GB', '20 GB', '50 GB', '100 GB', 'none', 'default',
        ];
    }
}
