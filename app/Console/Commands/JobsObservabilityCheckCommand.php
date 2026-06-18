<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Jobs\Services\TransportObservability;
use Illuminate\Console\Command;

class JobsObservabilityCheckCommand extends Command
{
    protected $signature = 'jobs:observability-check';

    protected $description = 'Emit structured alerts for missing webhooks and SSH vs Agent outcome parity';

    public function handle(TransportObservability $observability): int
    {
        $observability->runScheduledChecks();

        $this->info('Transport observability checks completed.');

        return self::SUCCESS;
    }
}
