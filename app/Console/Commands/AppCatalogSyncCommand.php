<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Product\Services\AppCatalogSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AppCatalogSyncCommand extends Command
{
    protected $signature = 'app-catalog:sync';

    protected $description = 'Import app catalog entries from the configured suite_catalog.json';

    public function handle(AppCatalogSyncService $service): int
    {
        try {
            $service->sync();
            $this->info('App catalog sync completed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('App catalog sync failed: '.$e->getMessage());
            Log::channel('security')->warning('app catalog sync failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
