<?php

declare(strict_types=1);

namespace App\Modules\Core\Ssh;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use Carbon\Carbon;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class SshConnectionPool
{
    /** @var array<string, array{ssh: SSH2, expires_at: Carbon}> */
    private static array $pool = [];

    private static bool $shutdownRegistered = false;

    public function __construct(
        private readonly int $ttlSeconds = 300,
        private readonly int $maxPoolSize = 5,
        private readonly int $connectTimeoutSeconds = 30,
    ) {
        $this->registerShutdown();
    }

    public function get(ClusterServer $cluster): SSH2
    {
        $id = (string) $cluster->id;

        if (isset(self::$pool[$id]) && self::$pool[$id]['expires_at']->isFuture()) {
            return self::$pool[$id]['ssh'];
        }

        if (isset(self::$pool[$id])) {
            $this->disconnectEntry($id);
        }

        if (count(self::$pool) >= $this->maxPoolSize) {
            $this->evictOldest();
        }

        $ssh = $this->createConnection($cluster);
        self::$pool[$id] = [
            'ssh' => $ssh,
            'expires_at' => Carbon::now()->addSeconds($this->ttlSeconds),
        ];

        return $ssh;
    }

    public function remove(string $clusterId): void
    {
        if (isset(self::$pool[$clusterId])) {
            $this->disconnectEntry($clusterId);
        }
    }

    private function createConnection(ClusterServer $cluster): SSH2
    {
        try {
            $ssh = new SSH2(
                $cluster->ssh_host,
                $cluster->ssh_port ?? 22,
                $this->connectTimeoutSeconds,
            );

            $key = PublicKeyLoader::load($cluster->ssh_private_key_encrypted);

            if (! $ssh->login($cluster->ssh_user ?? 'root', $key)) {
                throw new SshConnectionException(
                    "SSH login failed for cluster [{$cluster->id}] at {$cluster->ssh_host}"
                );
            }

            return $ssh;
        } catch (SshConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SshConnectionException(
                "SSH connection failed for cluster [{$cluster->id}]: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    private function disconnectEntry(string $id): void
    {
        try {
            self::$pool[$id]['ssh']->disconnect();
        } catch (\Throwable) {
        } finally {
            unset(self::$pool[$id]);
        }
    }

    private function evictOldest(): void
    {
        $oldestId = null;
        $oldestExpiry = null;

        foreach (self::$pool as $id => $entry) {
            if ($oldestExpiry === null || $entry['expires_at']->lt($oldestExpiry)) {
                $oldestExpiry = $entry['expires_at'];
                $oldestId = $id;
            }
        }

        if ($oldestId !== null) {
            $this->disconnectEntry($oldestId);
        }
    }

    private function registerShutdown(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;

        register_shutdown_function(function (): void {
            foreach (array_keys(self::$pool) as $id) {
                try {
                    self::$pool[$id]['ssh']->disconnect();
                } catch (\Throwable) {
                }
            }
            self::$pool = [];
        });
    }
}
