<?php

declare(strict_types=1);

namespace App\Modules\ClusterServers\Actions;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\SshClientInterface;

final class SyncWebhookSecretAction
{
    public function __construct(private readonly SshClientInterface $ssh) {}

    /**
     * Sends the plain webhook secret to the upstream nextcloud-saas-manager via SSH.
     *
     * The secret is passed via --payload-stdin (JSON) — never as a CLI argument.
     * Exceptions propagate to the caller to decide the error strategy.
     */
    public function execute(ClusterServer $cluster, string $plainSecret): void
    {
        $this->ssh->run(
            $cluster,
            'nextcloud-manage',
            ['config', 'set-webhook-secret', '--payload-stdin'],
            json_encode(['secret' => $plainSecret]),
        );
    }
}
