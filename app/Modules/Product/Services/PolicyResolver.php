<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\TenantUser;
use App\Modules\Product\Exceptions\PlanLimitExceededException;
use Illuminate\Support\Str;

final class PolicyResolver
{
    public function assertCanCreateUser(Customer $customer, Operator $actor): void
    {
        $customer->loadMissing('plan');
        $maxUsers = $customer->plan?->max_users;

        if ($maxUsers === null) {
            return;
        }

        $currentCount = TenantUser::query()
            ->where('customer_slug', $customer->slug)
            ->count();

        if ($currentCount >= $maxUsers) {
            $this->deny($customer, $actor, 'max_users');
        }
    }

    /**
     * @param  list<string>  $apps
     */
    public function assertCanEnableApps(Customer $customer, array $apps, Operator $actor): void
    {
        $customer->loadMissing('plan');
        $maxApps = $customer->plan?->max_apps;

        if ($maxApps === null) {
            return;
        }

        if (count($apps) > $maxApps) {
            $this->deny($customer, $actor, 'max_apps');
        }
    }

    private function deny(Customer $customer, Operator $actor, string $limit): void
    {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actor->id,
            'action' => 'policy_denied',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => ['limit' => $limit],
            'cluster_server_id' => $customer->cluster_server_id,
        ]);

        throw new PlanLimitExceededException($limit);
    }
}
