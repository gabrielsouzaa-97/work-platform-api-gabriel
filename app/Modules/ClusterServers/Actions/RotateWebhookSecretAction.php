<?php

declare(strict_types=1);

namespace App\Modules\ClusterServers\Actions;

use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use Illuminate\Support\Facades\DB;

final class RotateWebhookSecretAction
{
    public function __construct(
        private readonly WebhookSecretGenerator $generator,
    ) {}

    public function execute(ClusterServer $cluster): WebhookSecretHistory
    {
        return DB::transaction(function () use ($cluster) {
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

            $new = WebhookSecretHistory::create([
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

            return $new;
        });
    }
}
