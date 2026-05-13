<?php

declare(strict_types=1);

namespace App\Modules\ClusterServers\Services;

final class WebhookSecretGenerator
{
    public function generate(): string
    {
        return base64_encode(random_bytes(32));
    }
}
