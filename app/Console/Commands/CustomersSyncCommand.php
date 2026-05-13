<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClusterServer;
use App\Modules\Customers\Services\CustomerSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CustomersSyncCommand extends Command
{
    protected $signature = 'customers:sync {--cluster= : UUID of a specific cluster to sync}';

    protected $description = 'Sync local customer replica against upstream for all active clusters';

    public function handle(CustomerSyncService $svc): int
    {
        $clusters = $this->option('cluster')
            ? ClusterServer::where('id', $this->option('cluster'))->get()
            : ClusterServer::where('status', 'active')->get();

        foreach ($clusters as $cluster) {
            try {
                $report = $svc->sync($cluster);
                $this->info("[{$cluster->name}] inserted={$report->inserted} updated={$report->updated} deleted={$report->deleted}");
            } catch (\Throwable $e) {
                $this->error("[{$cluster->name}] sync failed: {$e->getMessage()}");
                Log::channel('security')->warning('customer sync failed', [
                    'cluster' => $cluster->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
