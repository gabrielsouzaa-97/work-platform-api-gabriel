<?php

declare(strict_types=1);

namespace App\Modules\Core\Ssh;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Dto\SshResponse;

interface SshClientInterface
{
    public function run(
        ClusterServer $cluster,
        string $cmd,
        array $args = [],
        ?string $payloadStdin = null,
        int $timeoutSec = 60
    ): SshResponse;

    public function runAsync(
        ClusterServer $cluster,
        string $cmd,
        array $args = [],
        ?string $payloadStdin = null
    ): SshResponse;

    /**
     * Initialize a staging directory on the server before SFTP upload (Canal A).
     * Calls `nextcloud-manage _ _ inbox-init --staging-id=<uuid>` via the command channel.
     */
    public function inboxInit(ClusterServer $cluster, string $stagingId): void;

    /**
     * Upload a branding file via SFTP to the staging inbox (Canal B — ncsaas-sftp).
     * Uses $cluster->sftp_user / sftp_private_key_encrypted.
     * Remote path is chroot-relative: /{stagingId}/{filename}
     */
    public function sftpUpload(ClusterServer $cluster, string $localPath, string $stagingId, string $filename): void;

    /**
     * @deprecated Use inboxInit() + sftpUpload() for branding files (new two-channel contract).
     */
    public function scpUpload(ClusterServer $cluster, string $localPath, string $remotePath): void;

    /**
     * Test raw SSH connectivity regardless of cluster status.
     * Bypasses the active-status guard — use ONLY for health checks.
     */
    public function ping(ClusterServer $cluster, int $timeoutSec = 10): SshResponse;
}
