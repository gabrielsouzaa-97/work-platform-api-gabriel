<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class ClusterServerObserver
{
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'key', 'pem', 'private'];

    public function created(ClusterServer $cluster): void
    {
        $this->log('create', $cluster, $this->sanitize($cluster->getAttributes()));
    }

    public function updated(ClusterServer $cluster): void
    {
        $this->log('update', $cluster, [
            'before' => $this->sanitize($cluster->getOriginal()),
            'after' => $this->sanitize($cluster->getChanges()),
        ]);
    }

    public function deleted(ClusterServer $cluster): void
    {
        $this->log('delete', $cluster, ['name' => $cluster->name]);
    }

    private function log(string $action, ClusterServer $cluster, array $payload): void
    {
        $actorId = Auth::id();

        if (! $actorId) {
            return;
        }

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actorId,
            'action' => "cluster_server.{$action}",
            'resource_type' => 'cluster_server',
            'resource_id' => $cluster->id,
            'payload' => $payload,
            'cluster_server_id' => $cluster->id,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    private function sanitize(array $attributes): array
    {
        return collect($attributes)
            ->map(function (mixed $value, string $key): mixed {
                foreach (self::SENSITIVE_KEYS as $pattern) {
                    if (str_contains(strtolower($key), $pattern)) {
                        return '[REDACTED]';
                    }
                }

                return $value;
            })
            ->all();
    }
}
