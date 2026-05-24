<?php

declare(strict_types=1);

namespace App\Modules\Core\Ssh;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

class SshClient implements SshClientInterface
{
    private const MAX_PAYLOAD_STDIN_BYTES = 262144; // 256 KB

    private const MAX_SCP_BYTES = 52428800;         // 50 MB

    private const RETRY_DELAYS_SECONDS = [1, 2, 4];

    private const CONNECTION_ERROR_PATTERNS = ['connection', 'timeout', 'refused', 'unreachable'];

    public function __construct(private readonly SshConnectionPool $pool) {}

    public function run(
        ClusterServer $cluster,
        string $cmd,
        array $args = [],
        ?string $payloadStdin = null,
        int $timeoutSec = 60
    ): SshResponse {
        $this->validateCluster($cluster);
        $this->validateTimeout($timeoutSec);
        $this->validatePayloadSize($payloadStdin);

        $command = $this->buildCommand($cmd, $args);
        $lastException = null;

        foreach (self::RETRY_DELAYS_SECONDS as $attempt => $delaySec) {
            try {
                return $this->executeCommand($cluster, $command, $payloadStdin, $timeoutSec);
            } catch (SshConnectionException $e) {
                $lastException = $e;
                $this->pool->remove((string) $cluster->id);

                $isLastAttempt = $attempt === count(self::RETRY_DELAYS_SECONDS) - 1;
                if (! $isLastAttempt) {
                    sleep($delaySec);
                }
            }
            // SshRemoteException and SshTimeoutException bubble up — no retry
        }

        throw $lastException ?? new SshConnectionException(
            "SSH execution failed after retries for cluster [{$cluster->id}]"
        );
    }

    public function runAsync(
        ClusterServer $cluster,
        string $cmd,
        array $args = [],
        ?string $payloadStdin = null
    ): SshResponse {
        $asyncArgs = array_merge($args, ['--async', '--json']);

        return $this->run($cluster, $cmd, $asyncArgs, $payloadStdin, timeoutSec: 5);
    }

    public function ping(ClusterServer $cluster, int $timeoutSec = 10): SshResponse
    {
        $this->validateTimeout($timeoutSec);

        // Send the raw command string — NOT via buildCommand. The remote server
        // has a ForceCommand that pattern-matches SSH_ORIGINAL_COMMAND expecting
        // the unquoted form `nextcloud-manage list --json`. buildCommand wraps
        // the binary name in escapeshellarg() producing `'nextcloud-manage'`
        // (with single quotes), which breaks the ForceCommand prefix match and
        // returns exit 101 for every sub-command.
        $command = 'nextcloud-manage list --json';

        $lastException = null;

        foreach (self::RETRY_DELAYS_SECONDS as $attempt => $delaySec) {
            try {
                return $this->executeCommand($cluster, $command, null, $timeoutSec);
            } catch (SshConnectionException $e) {
                $lastException = $e;
                $this->pool->remove((string) $cluster->id);

                $isLastAttempt = $attempt === count(self::RETRY_DELAYS_SECONDS) - 1;
                if (! $isLastAttempt) {
                    sleep($delaySec);
                }
            }
        }

        throw $lastException ?? new SshConnectionException(
            "SSH ping failed after retries for cluster [{$cluster->id}]"
        );
    }

    public function scpUpload(ClusterServer $cluster, string $localPath, string $remotePath): void
    {
        $this->validateCluster($cluster);

        if (! file_exists($localPath)) {
            throw new \InvalidArgumentException("Local file not found: {$localPath}");
        }

        $fileSize = filesize($localPath);
        if ($fileSize > self::MAX_SCP_BYTES) {
            throw new \InvalidArgumentException(
                "File size {$fileSize} bytes exceeds 50 MB SCP cap"
            );
        }

        try {
            $sftp = new SFTP($cluster->ssh_host, $cluster->ssh_port ?? 22, 30);
            $key = PublicKeyLoader::load($cluster->ssh_private_key_encrypted);

            if (! $sftp->login($cluster->ssh_user ?? 'root', $key)) {
                throw new SshConnectionException(
                    "SFTP login failed for cluster [{$cluster->id}]"
                );
            }

            if (! $sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
                throw new SshRemoteException(
                    "SCP upload failed: could not write to {$remotePath}",
                    remoteExitCode: 1,
                );
            }
        } catch (SshRemoteException|SshConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SshConnectionException(
                "SFTP connection failed for cluster [{$cluster->id}]: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    private function executeCommand(
        ClusterServer $cluster,
        string $command,
        ?string $payloadStdin,
        int $timeoutSec
    ): SshResponse {
        $ssh = $this->pool->get($cluster);
        $ssh->setTimeout($timeoutSec);

        $stdout = $payloadStdin !== null
            ? $this->execWithStdin($ssh, $command, $payloadStdin)
            : $ssh->exec($command);

        if ($stdout === false) {
            $errorMsg = $ssh->getLastError() ?? 'SSH exec returned false';
            $this->pool->remove((string) $cluster->id);

            if ($this->isConnectionError($errorMsg)) {
                throw new SshConnectionException(
                    "SSH connection error on cluster [{$cluster->id}]: {$errorMsg}"
                );
            }

            throw new SshConnectionException(
                "SSH exec failed for cluster [{$cluster->id}]: {$errorMsg}"
            );
        }

        $stderr = (string) ($ssh->getLastError() ?? '');
        $exitCode = $ssh->getExitStatus();
        $exitCode = ($exitCode === false) ? 0 : (int) $exitCode;

        $parsedJson = $this->tryParseJson((string) $stdout);

        $response = new SshResponse(
            stdout: (string) $stdout,
            stderr: $stderr,
            exitCode: $exitCode,
            parsedJson: $parsedJson,
        );

        // Log the clean command (without stdin payload) to avoid leaking secrets.
        $this->logExecution($cluster, $command, $response);

        if ($exitCode !== 0) {
            throw $this->buildRemoteException($cluster, $exitCode, $parsedJson);
        }

        return $response;
    }

    /**
     * Execute a command and deliver $payload via the SSH channel's stdin.
     *
     * The upstream ForceCommand filters shell metacharacters from SSH_ORIGINAL_COMMAND
     * (exit 100, "metacharacter detected"). Using a shell pipe (`printf … | cmd`) embeds
     * `|` in that string and triggers the filter.
     *
     * phpseclib3 supports an alternative protocol-level flow:
     *   1. exec($command, false) — sends the exec request and returns without entering
     *      the blocking read loop. Channel status is set to CHANNEL_DATA so write()
     *      can target it via get_interactive_channel().
     *   2. write($payload) — sends the payload to CHANNEL_EXEC stdin.
     *   3. sendEOF()        — sends SSH_MSG_CHANNEL_EOF so the remote process sees EOF.
     *   4. Read loop        — drains stdout until the channel closes (returns true).
     *
     * SSH_ORIGINAL_COMMAND received by the upstream is exactly $command — no metacharacters.
     */
    private function execWithStdin(SSH2 $ssh, string $command, string $payload): string|false
    {
        $started = $ssh->exec($command, false);
        if ($started === false) {
            return false;
        }

        $ssh->write($payload);
        $ssh->sendEOF();

        $stdout = '';
        while (true) {
            $chunk = $ssh->read('', SSH2::READ_NEXT);
            if ($chunk === true || $chunk === false) {
                break;
            }
            $stdout .= $chunk;
        }

        return $stdout;
    }

    private function buildCommand(string $cmd, array $args): string
    {
        if ($cmd === '') {
            throw new \InvalidArgumentException('SSH command cannot be empty');
        }

        // Remote ForceCommand runs SSH_ORIGINAL_COMMAND via shell word-splitting.
        // Spaced argv tokens must be single-quoted so bash keeps them as one token.
        // Double quotes may be stripped before word-split on some ForceCommand hops
        // (ISSUE-017). Do NOT escapeshellarg() the binary name — breaks ForceCommand
        // prefix match (see ping()).
        $parts = [$cmd];
        foreach ($args as $arg) {
            $parts[] = $this->formatRemoteArg((string) $arg);
        }

        return implode(' ', $parts);
    }

    /**
     * Quote an argv token for remote shell word-split when it contains whitespace.
     */
    private function formatRemoteArg(string $arg): string
    {
        if ($arg === '' || ! preg_match('/\s/u', $arg)) {
            return $arg;
        }

        return "'".str_replace("'", "'\\''", $arg)."'";
    }

    private function buildRemoteException(
        ClusterServer $cluster,
        int $exitCode,
        ?array $parsedJson = null,
    ): SshRemoteException|SshTimeoutException {
        $message = "Remote command failed on cluster [{$cluster->id}] with exit code {$exitCode}";

        return match ($exitCode) {
            124 => new SshTimeoutException($message),
            2 => new SshRemoteException($message, remoteExitCode: $exitCode, retryable: true, parsedJson: $parsedJson),
            3 => new SshRemoteException($message, remoteExitCode: $exitCode, idempotencyConflict: true, parsedJson: $parsedJson),
            4 => new SshRemoteException($message, remoteExitCode: $exitCode, stateConflict: true, parsedJson: $parsedJson),
            5 => new SshRemoteException($message, remoteExitCode: $exitCode, validationFailed: true, parsedJson: $parsedJson),
            99 => new SshRemoteException($message, remoteExitCode: $exitCode, notImplemented: true, parsedJson: $parsedJson),
            default => new SshRemoteException($message, remoteExitCode: $exitCode, parsedJson: $parsedJson),
        };
    }

    private function validateCluster(ClusterServer $cluster): void
    {
        if ($cluster->status !== 'active') {
            throw new SshConnectionException(
                "Cluster [{$cluster->id}] is not active (status: {$cluster->status})"
            );
        }
    }

    private function validateTimeout(int $timeoutSec): void
    {
        if ($timeoutSec < 1 || $timeoutSec > 300) {
            throw new \InvalidArgumentException(
                "Timeout must be between 1 and 300 seconds, got: {$timeoutSec}"
            );
        }
    }

    private function validatePayloadSize(?string $payloadStdin): void
    {
        if ($payloadStdin !== null && strlen($payloadStdin) > self::MAX_PAYLOAD_STDIN_BYTES) {
            throw new \InvalidArgumentException(
                'payloadStdin exceeds 256 KB — use scpUpload for large payloads'
            );
        }
    }

    private function tryParseJson(string $output): ?array
    {
        if ($output === '') {
            return null;
        }

        $decoded = json_decode($output, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            ? $decoded
            : null;
    }

    private function isConnectionError(string $message): bool
    {
        $lower = strtolower($message);

        foreach (self::CONNECTION_ERROR_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function logExecution(ClusterServer $cluster, string $command, SshResponse $response): void
    {
        $context = [
            'cluster_id' => $cluster->id,
            'host' => $cluster->ssh_host,
            'command' => $command,
            'exit_code' => $response->exitCode,
        ];

        if ($response->exitCode !== 0) {
            $context['stdout'] = mb_substr($response->stdout, 0, 2000);
            $context['stderr'] = mb_substr($response->stderr, 0, 500);
        }

        try {
            Log::channel('sshclient')->debug('SSH command executed', $context);
        } catch (\Throwable $e) {
            // Log channel failure (e.g. permission denied on rotating file) must never
            // propagate to callers — it would surface as a misleading "Erro inesperado"
            // toast even when the SSH command itself succeeded.
            report($e);
        }
    }
}
