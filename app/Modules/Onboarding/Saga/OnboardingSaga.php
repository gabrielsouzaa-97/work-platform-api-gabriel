<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Saga;

use App\Models\Job;
use App\Models\Onboarding;
use App\Models\Operator;
use App\Modules\Customers\Contracts\ProvisionsCustomer;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Onboarding\Dto\OnboardingSpec;
use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
use Illuminate\Support\Str;

final class OnboardingSaga
{
    public function __construct(
        private readonly ProvisionsCustomer $provisionCustomerAction,
    ) {}

    public function start(OnboardingSpec $spec, Operator $actor): Onboarding
    {
        $onboarding = $this->createOnboarding($spec);
        $result = $this->provisionCustomerAction->execute(
            $this->toProvisionPayload($spec),
            $actor,
        );

        $this->propagateCorrelationId($result['job'], $onboarding->id);
        $this->recordProvisionStep($onboarding, $result['job']->job_id);

        return $onboarding->fresh();
    }

    private function createOnboarding(OnboardingSpec $spec): Onboarding
    {
        $onboardingId = Str::uuid()->toString();

        return Onboarding::create([
            'id' => $onboardingId,
            'tenant_slug' => $spec->tenantSlug,
            'correlation_id' => $onboardingId,
            'state' => OnboardingState::Running,
            'current_step' => OnboardingStep::ProvisionTenant,
            'steps' => [],
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'api_key_id' => null,
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
}
