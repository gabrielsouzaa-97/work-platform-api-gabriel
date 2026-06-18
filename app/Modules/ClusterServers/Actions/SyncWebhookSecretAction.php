<?php

declare(strict_types=1);

namespace App\Modules\ClusterServers\Actions;

use App\Models\ClusterServer;
use App\Modules\Integration\Dto\SyncWebhookSecretCommand;
use App\Modules\Integration\Services\PlatformPortFactory;

final class SyncWebhookSecretAction
{
    public function __construct(private readonly PlatformPortFactory $platformPortFactory) {}

    public function execute(ClusterServer $cluster, string $plainSecret): void
    {
        $this->platformPortFactory
            ->for($cluster)
            ->syncWebhookSecret(new SyncWebhookSecretCommand($cluster, $plainSecret));
    }
}
