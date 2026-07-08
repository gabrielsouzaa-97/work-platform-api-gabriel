<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Modules\Customers\Services\TenantGroupSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TenantGroupsSyncCommand extends Command
{
    protected $signature = 'tenant-groups:sync {--customer= : Slug of a specific customer to sync}';

    protected $description = 'Reconcile local tenant_groups projection against upstream group:list for active customers';

    public function handle(TenantGroupSyncService $svc): int
    {
        $customers = $this->option('customer')
            ? Customer::where('slug', $this->option('customer'))->get()
            : Customer::where('status', 'active')->orderBy('slug')->get();

        $hadFailure = false;

        foreach ($customers as $customer) {
            try {
                $report = $svc->sync($customer);
                $this->info(sprintf(
                    '[%s] inserted=%d updated=%d deleted=%d drift=%d',
                    $customer->slug,
                    $report->inserted,
                    $report->updated,
                    $report->deleted,
                    $report->driftDetected,
                ));
            } catch (\Throwable $e) {
                $hadFailure = true;
                $this->error("[{$customer->slug}] sync failed: {$e->getMessage()}");
                Log::channel('security')->warning('tenant group sync failed', [
                    'customer' => $customer->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }
}
