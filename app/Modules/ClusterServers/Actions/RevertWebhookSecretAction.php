<?php

declare(strict_types=1);

namespace App\Modules\ClusterServers\Actions;

use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;
use Illuminate\Support\Facades\DB;

final class RevertWebhookSecretAction
{
    public function execute(ClusterServer $cluster): void
    {
        DB::transaction(function () use ($cluster) {
            $current = WebhookSecretHistory::where('cluster_server_id', $cluster->id)
                ->whereNull('valid_until')
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw new \RuntimeException("ClusterServer {$cluster->id} sem secret atual no histórico");
            }

            $graceRecord = WebhookSecretHistory::where('cluster_server_id', $cluster->id)
                ->whereNotNull('valid_until')
                ->where('valid_until', '>', now())
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            if (! $graceRecord) {
                throw new \RuntimeException('Nenhum secret em grace period disponível para reverter');
            }

            // Remove current version and restore the grace one as active
            $current->delete();

            $graceRecord->update(['valid_until' => null]);

            $cluster->webhook_secret_encrypted = $graceRecord->secret_encrypted;
            $cluster->webhook_secret_version = $graceRecord->version;
            $cluster->save();
        });
    }
}
