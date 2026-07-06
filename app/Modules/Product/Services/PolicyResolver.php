<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\TenantUser;
use App\Modules\Product\Exceptions\PlanLimitExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PolicyResolver
{
    public function assertCanCreateUser(Customer $customer, Operator $actor): void
    {
        $limit = $this->detectUserCreateViolation($customer);

        if ($limit !== null) {
            $this->deny($customer, $actor, $limit);
        }
    }

    /**
     * Must be called from within an active DB transaction (e.g. job persist).
     */
    public function assertCanCreateUserForJobPersist(Customer $customer, Operator $actor): void
    {
        $limit = $this->detectUserCreateViolation($customer);

        if ($limit !== null) {
            $this->deny($customer, $actor, $limit);
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

    private function detectUserCreateViolation(Customer $customer): ?string
    {
        return DB::transaction(function () use ($customer): ?string {
            $locked = Customer::query()
                ->where('slug', $customer->slug)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('plan');
            $maxUsers = $locked->plan?->max_users;

            if ($maxUsers === null) {
                return null;
            }

            if ($this->countUserSlotsInUse($locked->slug) >= $maxUsers) {
                return 'max_users';
            }

            return null;
        });
    }

    private function countUserSlotsInUse(string $customerSlug): int
    {
        $projected = TenantUser::query()
            ->where('customer_slug', $customerSlug)
            ->count();

        $inflight = Job::query()
            ->where('customer_slug', $customerSlug)
            ->where('job_type', 'users:create')
            ->whereIn('state', ['queued', 'running'])
            ->count();

        return $projected + $inflight;
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
