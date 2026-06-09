<?php

declare(strict_types=1);

namespace App\Modules\ClusterServers\Actions;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class RotateWebhookSecretAction
{
    public function __construct(
        private readonly WebhookSecretGenerator $generator,
        private readonly SyncWebhookSecretAction $syncAction,
    ) {}

    public function execute(ClusterServer $cluster, ?string $actorId = null): WebhookSecretHistory
    {
        $new = DB::transaction(function () use ($cluster) {
            $current = WebhookSecretHistory::where('cluster_server_id', $cluster->id)
                ->whereNull('valid_until')
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw new \RuntimeException("ClusterServer {$cluster->id} sem secret atual no histórico");
            }

            $graceHours = config('services.webhook.grace_period_hours', 24);
            $current->update(['valid_until' => now()->addHours($graceHours)]);

            $newSecret = $this->generator->generate();
            $newVersion = $current->version + 1;

            $historyEntry = WebhookSecretHistory::create([
                'cluster_server_id' => $cluster->id,
                'secret_encrypted' => $newSecret,
                'version' => $newVersion,
                'valid_from' => now(),
                'valid_until' => null,
            ]);

            $cluster->update([
                'webhook_secret_encrypted' => $newSecret,
                'webhook_secret_version' => $newVersion,
            ]);

            return $historyEntry;
        });

        // Sync new secret with upstream after commit.
        // $cluster->webhook_secret_encrypted returns the plain value (cast decrypts on read).
        // On SSH failure: grace period ensures the old secret remains valid for 24h.
        try {
            $this->syncAction->execute($cluster, $cluster->webhook_secret_encrypted);
        } catch (\Throwable $e) {
            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actorId,
                'action' => 'cluster_server.secret_sync_failed',
                'resource_type' => 'cluster_server',
                'resource_id' => $cluster->id,
                'payload' => ['error' => $e->getMessage(), 'version' => $new->version],
            ]);
            Log::channel('security')->warning('webhook.secret_sync_failed', [
                'cluster_id' => $cluster->id,
                'version' => $new->version,
                'error' => $e->getMessage(),
            ]);
            // Not rethrown — DB rotation succeeded; upstream sync can be retried manually.
        }

        return $new;
    }
}
