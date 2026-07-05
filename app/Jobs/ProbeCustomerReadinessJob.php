<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use App\Modules\Integration\Dto\ReadinessReport;
use App\Modules\Onboarding\Saga\OnboardingSaga;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

final class ProbeCustomerReadinessJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 120;

    public int $tries;

    public readonly int $deadlineTimestamp;

    public function __construct(
        public readonly string $customerSlug,
        ?int $deadlineTimestamp = null,
    ) {
        $this->deadlineTimestamp = $deadlineTimestamp ?? self::deadlineTimestamp();
        $this->tries = (int) config('services.customer_readiness.max_attempts', 10);
    }

    public static function deadlineTimestamp(): int
    {
        return now()->addSeconds((int) config('services.customer_readiness.max_wait_seconds', 1200))->timestamp;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(CustomerReadinessProbe $probe): void
    {
        $customer = Customer::find($this->customerSlug);

        if ($customer === null || $customer->status !== CustomerLifecycleStatus::PROVISIONING_FINISHING) {
            return;
        }

        if ($this->isDeadlineExceeded()) {
            $this->markTimedOut($customer);

            return;
        }

        $report = $probe->probe($customer);

        if ($report->ready) {
            $customer->update(['status' => CustomerLifecycleStatus::ACTIVE]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => null,
                'action' => 'customer_readiness_confirmed',
                'resource_type' => 'customer',
                'resource_id' => $customer->slug,
                'payload' => ['probe' => 'occ-exec user:list'],
                'cluster_server_id' => $customer->cluster_server_id,
                'job_id' => null,
                'ip' => null,
            ]);

            app(OnboardingSaga::class)->advanceAfterProvisionForSlug($customer->slug);

            return;
        }

        $this->recordProbeFailure($customer, $report);

        if ($this->attempts() >= $this->tries) {
            $this->markTimedOut($customer);

            return;
        }

        $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);
    }

    private function isDeadlineExceeded(): bool
    {
        return now()->timestamp >= $this->deadlineTimestamp;
    }

    private function recordProbeFailure(Customer $customer, ReadinessReport $report): void
    {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'customer_readiness_probe',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'attempt' => $this->attempts(),
                'error' => $report->error ?? 'readiness gate failed',
                'probe' => $report->probe ?? 'occ-exec user:list',
            ],
            'cluster_server_id' => $customer->cluster_server_id,
            'job_id' => null,
            'ip' => null,
        ]);
    }

    private function markTimedOut(Customer $customer): void
    {
        $customer->update(['status' => 'failed']);

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'customer_readiness_timeout',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'attempts' => $this->attempts(),
                'deadline' => $this->deadlineTimestamp,
            ],
            'cluster_server_id' => $customer->cluster_server_id,
            'job_id' => null,
            'ip' => null,
        ]);
    }
}
