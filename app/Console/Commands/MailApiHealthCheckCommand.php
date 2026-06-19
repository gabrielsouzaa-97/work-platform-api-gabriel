<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Mail\Services\MailApiClient;
use Illuminate\Console\Command;

final class MailApiHealthCheckCommand extends Command
{
    protected $signature = 'mail-api:health-check';

    protected $description = 'Check connectivity to the work-mail-api health endpoint';

    public function __construct(private readonly MailApiClient $mailApiClient)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->mailApiClient->isHealthy()) {
            $this->info('Mail API is healthy');

            return self::SUCCESS;
        }

        $this->error('Mail API is unavailable');

        return self::FAILURE;
    }
}
