<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WebhookSecretHistory;
use Illuminate\Console\Command;

class CleanExpiredWebhookSecretsCommand extends Command
{
    protected $signature = 'webhook-secrets:clean';

    protected $description = 'Remove webhook secret history entries expired for more than 30 days';

    public function handle(): int
    {
        $deleted = WebhookSecretHistory::whereNotNull('valid_until')
            ->where('valid_until', '<', now()->subDays(30))
            ->delete();

        $this->info("Removed {$deleted} expired webhook secret record(s).");

        return self::SUCCESS;
    }
}
