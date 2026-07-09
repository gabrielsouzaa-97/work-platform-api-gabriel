<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Saga;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Onboarding;
use App\Models\Operator;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Contracts\ProvisionsCustomer;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Customers\Support\UserCreateStdinPayload;
use App\Modules\Integration\Dto\SetBrandingCommand;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Services\PlatformPortFactory;
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
        private readonly LifecycleAsyncAction $lifecycleAsync,
        private readonly PlatformPortFactory $platformPortFactory,
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
        $onboarding = $this->findWaitReadinessOnboarding($tenantSlug);

        if ($onboarding === null) {
            return;
        }

        $this->advanceAfterProvision($onboarding);
    }

    public function failWaitReadinessForSlug(string $tenantSlug, string $reason): void
    {
        $onboarding = $this->findWaitReadinessOnboarding($tenantSlug);

        if ($onboarding === null) {
            return;
        }

        $this->markStepFailed($onboarding, OnboardingStep::WaitReadiness, $reason);
    }

    public function handleTerminalJob(Job $job, string $canonicalState): void
    {
        if ($job->correlation_id === null) {
            return;
        }

        $onboarding = Onboarding::query()->find($job->correlation_id);

        if ($onboarding === null) {
            return;
        }

        $step = $this->stepForJob($job);

        if ($step === null || $step === OnboardingStep::ProvisionTenant) {
            return;
        }

        if (in_array($canonicalState, ['failed', 'cancelled'], true)) {
            $this->markStepFailed($onboarding, $step, $canonicalState);

            return;
        }

        if ($canonicalState !== 'success') {
            return;
        }

        match ($step) {
            OnboardingStep::CreateAdmin => $this->advanceAfterCreateAdmin($onboarding),
            OnboardingStep::EnableApps => $this->advanceAfterEnableApps($onboarding),
            default => null,
        };
    }

    public function markStepFailed(Onboarding $onboarding, OnboardingStep $step, string $reason): void
    {
        $steps = $this->stepsFor($onboarding);
        $steps[$step->value] = $this->mergeStepMeta(
            $steps[$step->value] ?? [],
            ['status' => 'failed', 'reason' => $reason],
        );

        $onboarding->update([
            'state' => OnboardingState::Failed,
            'steps' => $steps,
        ]);
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

    public function advanceAfterCreateAdmin(Onboarding $onboarding): void
    {
        if ($onboarding->current_step !== OnboardingStep::CreateAdmin) {
            return;
        }

        $customer = $this->resolveCustomer($onboarding);

        if ($customer === null) {
            return;
        }

        $steps = $this->stepsFor($onboarding);
        $steps['create_admin'] = $this->mergeStepMeta(
            $steps['create_admin'] ?? [],
            ['status' => 'completed'],
        );

        $onboarding->update([
            'current_step' => OnboardingStep::EnableApps,
            'steps' => $steps,
        ]);

        $this->dispatchEnableApps($onboarding->fresh(), $customer);
    }

    public function advanceAfterEnableApps(Onboarding $onboarding): void
    {
        if ($onboarding->current_step !== OnboardingStep::EnableApps) {
            return;
        }

        $steps = $this->stepsFor($onboarding);
        $steps['enable_apps'] = $this->mergeStepMeta(
            $steps['enable_apps'] ?? [],
            ['status' => 'completed'],
        );

        $onboarding->update([
            'current_step' => OnboardingStep::SetBranding,
            'steps' => $steps,
        ]);

        $this->applyBranding($onboarding->fresh());
    }

    private function advanceToCreateAdmin(Onboarding $onboarding): void
    {
        $customer = $this->resolveCustomer($onboarding);

        if ($customer === null) {
            return;
        }

        $steps = $this->stepsFor($onboarding);
        $steps['provision_tenant'] = $this->mergeStepMeta(
            $steps['provision_tenant'] ?? [],
            ['status' => 'completed'],
        );
        $steps['wait_readiness'] = ['status' => 'completed'];

        $onboarding->update([
            'current_step' => OnboardingStep::CreateAdmin,
            'steps' => $steps,
        ]);

        $this->dispatchCreateAdmin($onboarding->fresh(), $customer);
    }

    private function dispatchCreateAdmin(Onboarding $onboarding, Customer $customer): void
    {
        $admin = $onboarding->admin_payload;

        if (! is_array($admin) || empty($admin['username']) || empty($admin['password'])) {
            $this->markStepFailed($onboarding, OnboardingStep::CreateAdmin, 'missing_admin_credentials');

            return;
        }

        $stdin = UserCreateStdinPayload::build(
            password: (string) $admin['password'],
            displayName: (string) ($admin['username'] ?? ''),
            email: (string) ($admin['email'] ?? ''),
        );

        try {
            $job = $this->lifecycleAsync->execute(
                $customer,
                'users:create',
                [(string) $admin['username']],
                $stdin,
                $this->resolveActor($onboarding),
            );
        } catch (\Throwable) {
            $this->markStepFailed($onboarding, OnboardingStep::CreateAdmin, 'dispatch_failed');

            return;
        }

        $this->attachJobToStep($onboarding, OnboardingStep::CreateAdmin, $job);
    }

    private function dispatchEnableApps(Onboarding $onboarding, Customer $customer): void
    {
        $apps = is_array($onboarding->apps_enabled) ? $onboarding->apps_enabled : [];

        if ($apps === []) {
            $this->markStepFailed($onboarding, OnboardingStep::EnableApps, 'missing_apps');

            return;
        }

        try {
            $job = $this->lifecycleAsync->execute(
                $customer,
                'apps:enable',
                [implode(',', $apps)],
                null,
                $this->resolveActor($onboarding),
            );
        } catch (\Throwable) {
            $this->markStepFailed($onboarding, OnboardingStep::EnableApps, 'dispatch_failed');

            return;
        }

        $this->attachJobToStep($onboarding, OnboardingStep::EnableApps, $job);
    }

    private function applyBranding(Onboarding $onboarding): void
    {
        $brandingFields = is_array($onboarding->branding_fields) ? $onboarding->branding_fields : null;

        if ($brandingFields === null || $brandingFields === []) {
            $this->markCompleted($onboarding);

            return;
        }

        $customer = $this->resolveCustomer($onboarding);

        if ($customer === null) {
            return;
        }

        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null) {
            $this->markStepFailed($onboarding, OnboardingStep::SetBranding, 'cluster_unreachable');

            return;
        }

        try {
            $this->platformPortFactory
                ->for($cluster)
                ->setBranding(new SetBrandingCommand($customer, $brandingFields));
        } catch (CapabilityBlockedException) {
            $this->skipBrandingWhenBlocked($onboarding);

            return;
        } catch (\Throwable) {
            $this->markStepFailed($onboarding, OnboardingStep::SetBranding, 'branding_failed');

            return;
        }

        $steps = $this->stepsFor($onboarding);
        $steps['set_branding'] = ['status' => 'completed'];
        $onboarding->update([
            'state' => OnboardingState::Completed,
            'steps' => $steps,
        ]);
    }

    private function markCompleted(Onboarding $onboarding): void
    {
        $onboarding->update(['state' => OnboardingState::Completed]);
    }

    private function attachJobToStep(Onboarding $onboarding, OnboardingStep $step, Job $job): void
    {
        $job->update(['correlation_id' => $onboarding->correlation_id]);

        $steps = $this->stepsFor($onboarding);
        $steps[$step->value] = [
            'status' => 'running',
            'job_id' => $job->job_id,
        ];

        $onboarding->update(['steps' => $steps]);
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
            'admin_payload' => [
                'username' => $spec->adminUsername,
                'password' => $spec->adminPassword,
                'email' => $spec->adminEmail,
            ],
            'apps_enabled' => $spec->apps,
            'branding_fields' => $spec->brandingFields,
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

    private function findWaitReadinessOnboarding(string $tenantSlug): ?Onboarding
    {
        return Onboarding::query()
            ->where('tenant_slug', $tenantSlug)
            ->where('current_step', OnboardingStep::WaitReadiness)
            ->whereIn('state', [OnboardingState::Running, OnboardingState::Pending])
            ->first();
    }

    private function resolveCustomer(Onboarding $onboarding): ?Customer
    {
        return Customer::query()->find($onboarding->tenant_slug);
    }

    private function resolveActor(Onboarding $onboarding): Operator
    {
        $operator = $onboarding->apiKey?->operator;

        if ($operator !== null) {
            return $operator;
        }

        return Operator::query()->firstOrFail();
    }

    private function stepForJob(Job $job): ?OnboardingStep
    {
        return match ($job->job_type) {
            'provision' => OnboardingStep::ProvisionTenant,
            'user_create' => OnboardingStep::CreateAdmin,
            'apps_enable' => OnboardingStep::EnableApps,
            default => null,
        };
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
