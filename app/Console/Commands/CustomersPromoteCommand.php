<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Modules\Customers\Support\CustomerLifecycleAction;
use App\Modules\Customers\Support\CustomerLifecycleMatrix;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CustomersPromoteCommand extends Command
{
    protected $signature = 'customers:promote {slug}';

    protected $description = 'Manually promote a customer from provisioning_finishing to active';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        $customer = Customer::query()->find($slug);

        if ($customer === null) {
            $this->error("Customer not found: {$slug}");

            return self::FAILURE;
        }

        if (! CustomerLifecycleMatrix::allows($customer->status, CustomerLifecycleAction::PromoteManual)) {
            $this->error("Customer {$slug} cannot be promoted manually (current: {$customer->status})");

            return self::FAILURE;
        }

        $customer->update([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'failure_reason' => null,
        ]);

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'customer_promoted_manual',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'from_status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
                'to_status' => CustomerLifecycleStatus::ACTIVE,
            ],
            'cluster_server_id' => $customer->cluster_server_id,
            'job_id' => null,
            'ip' => null,
        ]);

        $this->info("Customer {$slug} promoted to active.");

        return self::SUCCESS;
    }
}
