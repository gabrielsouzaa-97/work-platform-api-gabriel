<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Billing\Services\WhmcsProvisionService;
use App\Modules\Customers\Actions\TenantResumeAction;
use App\Modules\Customers\Actions\TenantSuspendAction;
use Illuminate\Support\Facades\Cache;

final class ProcessWhmcsWebhookAction
{
    private const int IDEMPOTENCY_TTL_SECONDS = 86400;

    public function __construct(
        private readonly WhmcsProvisionService $provisionService,
        private readonly TenantSuspendAction $suspendAction,
        private readonly TenantResumeAction $resumeAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function execute(array $payload, Operator $actor): array
    {
        $event = (string) ($payload['event'] ?? '');

        if ($this->isDuplicate($event, $payload)) {
            return ['status' => 'duplicate', 'event' => $event];
        }

        $result = match ($event) {
            'InvoicePaid' => $this->handleInvoicePaid($payload, $actor),
            'ModuleSuspend', 'TrialExpired' => $this->handleSuspend($payload, $actor),
            'ModuleUnsuspend' => $this->handleResume($payload, $actor),
            default => ['status' => 'ignored', 'event' => $event],
        };

        if (($result['status'] ?? '') !== 'ignored') {
            $this->markProcessed($event, $payload);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleInvoicePaid(array $payload, Operator $actor): array
    {
        // N22.1: InvoicePaid confirms payment; welcome email remains in ProbeCustomerReadinessJob.
        $provision = $this->provisionService->provisionFromWebhook($payload, $actor);

        return [
            'status' => 'provisioned',
            'tenant_slug' => $provision['customer']->slug,
            'job_id' => $provision['job_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleSuspend(array $payload, Operator $actor): array
    {
        $customer = $this->resolveCustomer($payload);
        $job = $this->suspendAction->execute($customer, $actor);

        return [
            'status' => 'suspended',
            'tenant_slug' => $customer->slug,
            'job_id' => $job->job_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleResume(array $payload, Operator $actor): array
    {
        $customer = $this->resolveCustomer($payload);
        $job = $this->resumeAction->execute($customer, $actor);

        return [
            'status' => 'resumed',
            'tenant_slug' => $customer->slug,
            'job_id' => $job->job_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCustomer(array $payload): Customer
    {
        $slug = (string) ($payload['tenant_slug'] ?? $payload['slug'] ?? '');

        if ($slug === '') {
            throw new \InvalidArgumentException('WHMCS webhook missing tenant_slug');
        }

        return Customer::query()->findOrFail($slug);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isDuplicate(string $event, array $payload): bool
    {
        $key = $this->idempotencyKey($event, $payload);

        return $key !== null && Cache::has($key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markProcessed(string $event, array $payload): void
    {
        $key = $this->idempotencyKey($event, $payload);

        if ($key !== null) {
            Cache::put($key, true, self::IDEMPOTENCY_TTL_SECONDS);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function idempotencyKey(string $event, array $payload): ?string
    {
        $invoiceId = $payload['invoice_id'] ?? null;
        $serviceId = $payload['service_id'] ?? null;

        if ($invoiceId !== null && $invoiceId !== '') {
            return "whmcs:webhook:{$event}:invoice:{$invoiceId}";
        }

        if ($serviceId !== null && $serviceId !== '') {
            return "whmcs:webhook:{$event}:service:{$serviceId}";
        }

        return null;
    }
}
