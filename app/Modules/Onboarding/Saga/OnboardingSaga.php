<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Saga;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Onboarding;
use App\Models\Operator;
use App\Modules\Customers\Contracts\ProvisionsCustomer;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Onboarding\Dto\OnboardingSpec;
use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
use Illuminate\Support\Str;

final class OnboardingSaga
{
    private const TENANT_NOT_READY_REASON = 'tenant_not_ready';

    private const CAPABILITY_NOT_AVAILABLE = 'capability_not_available';

    public function __construct(
        private readonly ProvisionsCustomer $provisionCustomerAction,
        private readonly CustomerReadinessProbe $readinessProbe,
    ) {}

    public function start(
        OnboardingSpec $spec,
        Operator $actor,
        ?string $idempotencyKey = null,
        ?string $apiKeyId = null,
    ): Onboarding {
        $onboarding = $this->createOnboarding($spec, $idempotencyKey, $apiKeyId);
        $result = $this->provisionCustomerAction->execute(
            $this->toProvisionPayload($spec),
            $actor,
        );

        $this->propagateCorrelationId($result['job'], $onboarding->id);
        $this->recordProvisionStep($onboarding, $result['job']->job_id);

        return $onboarding->fresh();
    }

    public function advanceAfterProvision(Onboarding $onboarding): void
    {
        if ($onboarding->current_step !== OnboardingStep::WaitReadiness) {
            return;
        }

        $customer = Customer::query()->find($onboarding->tenant_slug);

        if ($customer === null) {
            return;
        }

        if (! $this->readinessProbe->isReady($customer)) {
            $this->markWaitReadinessPending($onboarding);

            return;
        }

        $this->advanceToCreateAdmin($onboarding);
    }

    public function advanceAfterProvisionForSlug(string $tenantSlug): void
    {
        $onboarding = Onboarding::query()
            ->where('tenant_slug', $tenantSlug)
            ->where('current_step', OnboardingStep::WaitReadiness)
            ->whereIn('state', [OnboardingState::Running, OnboardingState::Pending])
            ->first();

        if ($onboarding === null) {
            return;
        }

        $this->advanceAfterProvision($onboarding);
    }

    public function skipBrandingWhenBlocked(Onboarding $onboarding): void
    {
        if ($onboarding->current_step !== OnboardingStep::SetBranding) {
            return;
        }

        $steps = $this->stepsFor($onboarding);
        $steps['set_branding'] = [
            'status' => 'skipped',
            'reason' => self::CAPABILITY_NOT_AVAILABLE,
        ];

        $onboarding->update([
            'state' => OnboardingState::Partial,
            'steps' => $steps,
        ]);
    }

    private function createOnboarding(
        OnboardingSpec $spec,
        ?string $idempotencyKey,
        ?string $apiKeyId,
    ): Onboarding {
        $onboardingId = Str::uuid()->toString();

        return Onboarding::create([
            'id' => $onboardingId,
            'tenant_slug' => $spec->tenantSlug,
            'correlation_id' => $onboardingId,
            'state' => OnboardingState::Running,
            'current_step' => OnboardingStep::ProvisionTenant,
            'steps' => [],
            'idempotency_key' => $idempotencyKey ?? hash('sha256', Str::uuid()->toString()),
            'api_key_id' => $apiKeyId,
        ]);
    }

    private function toProvisionPayload(OnboardingSpec $spec): ProvisionPayload
    {
        return new ProvisionPayload(
            slug: $spec->tenantSlug,
            domain: $spec->domain,
            clusterServerId: $spec->clusterServerId,
            apps: $spec->apps,
            fullApps: $spec->fullApps,
            logoPath: $spec->logoPath,
            backgroundPath: $spec->backgroundPath,
        );
    }

    private function propagateCorrelationId(Job $job, string $correlationId): void
    {
        $job->update(['correlation_id' => $correlationId]);
    }

    private function recordProvisionStep(Onboarding $onboarding, string $jobId): void
    {
        $onboarding->update([
            'current_step' => OnboardingStep::WaitReadiness,
            'steps' => [
                'provision_tenant' => [
                    'job_id' => $jobId,
                ],
            ],
        ]);
    }

    private function markWaitReadinessPending(Onboarding $onboarding): void
    {
        $steps = $this->stepsFor($onboarding);
        $steps['provision_tenant'] = $this->mergeStepMeta(
            $steps['provision_tenant'] ?? [],
            ['status' => 'completed'],
        );
        $steps['wait_readiness'] = [
            'status' => 'pending',
            'reason' => self::TENANT_NOT_READY_REASON,
            'retry_after' => $this->readinessRetryAfterSeconds(),
        ];

        $onboarding->update([
            'current_step' => OnboardingStep::WaitReadiness,
            'steps' => $steps,
        ]);
    }

    private function advanceToCreateAdmin(Onboarding $onboarding): void
    {
        $steps = $this->stepsFor($onboarding);
        $steps['provision_tenant'] = $this->mergeStepMeta(
            $steps['provision_tenant'] ?? [],
            ['status' => 'completed'],
        );
        $steps['wait_readiness'] = ['status' => 'completed'];
        $steps['create_admin'] = ['status' => 'pending'];

        $onboarding->update([
            'current_step' => OnboardingStep::CreateAdmin,
            'steps' => $steps,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stepsFor(Onboarding $onboarding): array
    {
        return is_array($onboarding->steps) ? $onboarding->steps : [];
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function mergeStepMeta(array $existing, array $meta): array
    {
        return array_merge($existing, $meta);
    }

    private function readinessRetryAfterSeconds(): int
    {
        return (int) config('services.customer_readiness.retry_after_seconds', 60);
    }
}
