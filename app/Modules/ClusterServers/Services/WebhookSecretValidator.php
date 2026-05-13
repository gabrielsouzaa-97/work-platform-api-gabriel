<?php

declare(strict_types=1);

namespace App\Modules\ClusterServers\Services;

use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;

final class WebhookSecretValidator
{
    /**
     * Returns true if $signature matches any currently-valid secret (current or in grace period).
     *
     * Accepts both the active secret (valid_until IS NULL) and secrets still within their
     * 24-hour grace window so that upstream can complete reconfiguration without downtime.
     */
    public function valid(ClusterServer $cluster, string $signature, string $body): bool
    {
        $secrets = WebhookSecretHistory::where('cluster_server_id', $cluster->id)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->pluck('secret_encrypted');

        foreach ($secrets as $secret) {
            $expected = 'sha256='.hash_hmac('sha256', $body, $secret);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
