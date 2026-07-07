<?php

declare(strict_types=1);

namespace App\Modules\Core\Ssh;

use App\Jobs\SimulateFakeJobWebhookJob;
use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Dto\SshResponse;
use Illuminate\Support\Str;

/**
 * Local/dev SSH transport that simulates nextcloud-manage without a real cluster.
 * Activated when services.ssh.driver=fake.
 */
final class FakeSshClient implements SshClientInterface
{
    public function __construct(
        private readonly bool $autoCompleteJobs,
        private readonly int $jobDelaySeconds,
    ) {}

    public function run(
        ClusterServer $cluster,
        string $cmd,
        array $args = [],
        ?string $payloadStdin = null,
        int $timeoutSec = 60
    ): SshResponse {
        if ($cluster->status !== 'active') {
            throw new Exceptions\SshConnectionException(
                "Cluster [{$cluster->id}] is not active (status: {$cluster->status})"
            );
        }

        if ($cmd === 'nextcloud-manage' && ($args[0] ?? '') === 'list') {
            return $this->jsonResponse([
                'schema_version' => '1',
                'instances' => [],
            ]);
        }

        if ($cmd === 'nextcloud-manage' && ($args[0] ?? '') === 'job') {
            return $this->handleJobCommand($args);
        }

        if ($cmd === 'nextcloud-manage' && ($args[0] ?? '') === 'config') {
            return $this->jsonResponse(['ok' => true]);
        }

        if ($cmd === 'nextcloud-manage' && ($args[2] ?? '') === 'occ-exec') {
            return $this->handleOccExec($args);
        }

        if ($cmd === 'nextcloud-manage' && ($args[2] ?? '') === 'inbox-init') {
            return $this->jsonResponse(['ok' => true]);
        }

        return $this->jsonResponse(['ok' => true]);
    }

    public function runAsync(
        ClusterServer $cluster,
        string $cmd,
        array $args = [],
        ?string $payloadStdin = null
    ): SshResponse {
        $jobId = (string) Str::uuid();
        $response = $this->jsonResponse(['job_id' => $jobId]);

        if ($this->autoCompleteJobs) {
            SimulateFakeJobWebhookJob::dispatch($jobId)
                ->delay(now()->addSeconds(max(0, $this->jobDelaySeconds)));
        }

        return $response;
    }

    public function inboxInit(ClusterServer $cluster, string $stagingId): void
    {
        // No-op: fake staging inbox is always ready.
    }

    public function sftpUpload(
        ClusterServer $cluster,
        string $localPath,
        string $stagingId,
        string $filename
    ): void {
        // No-op: branding files are already persisted locally by the deployer.
    }

    public function scpUpload(ClusterServer $cluster, string $localPath, string $remotePath): void
    {
        // No-op for legacy path.
    }

    public function ping(ClusterServer $cluster, int $timeoutSec = 10): SshResponse
    {
        return $this->jsonResponse([
            'schema_version' => '1',
            'instances' => [],
        ]);
    }

    /**
     * @param  array<int, string>  $args
     */
    private function handleJobCommand(array $args): SshResponse
    {
        $action = $args[2] ?? '';

        return match ($action) {
            'status' => $this->jsonResponse(['state' => 'success']),
            'logs' => $this->jsonResponse(['lines' => ['[fake] job completed successfully']]),
            'cancel' => $this->jsonResponse(['state' => 'cancelled']),
            default => $this->jsonResponse(['ok' => true]),
        };
    }

    /**
     * @param  array<int, string>  $args
     */
    private function handleOccExec(array $args): SshResponse
    {
        $occSubcmd = $args[2] ?? '';

        return match ($occSubcmd) {
            'app:list' => $this->jsonResponse([
                'enabled' => [
                    'mework360_memail' => true,
                    'me360_theme' => true,
                ],
            ]),
            'user:list' => $this->jsonResponse([]),
            'config:app:get' => $this->configAppGetResponse($args),
            'theming:config' => $this->jsonResponse(['ok' => true]),
            default => $this->jsonResponse([]),
        };
    }

    /**
     * @param  array<int, string>  $args
     */
    private function configAppGetResponse(array $args): SshResponse
    {
        $key = $args[4] ?? '';

        $value = match ($key) {
            'externalLocation' => 'https://cloud.example/roundcube',
            'forceSSO' => 'yes',
            default => '',
        };

        return new SshResponse(
            stdout: $value,
            stderr: '',
            exitCode: 0,
            parsedJson: ['value' => $value],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jsonResponse(array $payload, int $exitCode = 0): SshResponse
    {
        $stdout = json_encode($payload, JSON_THROW_ON_ERROR);

        return new SshResponse(
            stdout: $stdout,
            stderr: '',
            exitCode: $exitCode,
            parsedJson: $payload,
        );
    }
}
