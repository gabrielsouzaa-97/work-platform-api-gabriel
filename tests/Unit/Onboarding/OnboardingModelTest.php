<?php

declare(strict_types=1);

use App\Models\Onboarding;
use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function onboardingStateValues(): array
{
    return ['pending', 'running', 'completed', 'failed', 'partial'];
}

function onboardingStepValues(): array
{
    return ['provision_tenant', 'wait_readiness', 'create_admin', 'enable_apps', 'set_branding'];
}

it('defines OnboardingState backed enum cases', function (): void {
    expect(enum_exists(OnboardingState::class))->toBeTrue()
        ->and(collect(OnboardingState::cases())->map->value->sort()->values()->all())
        ->toBe(collect(onboardingStateValues())->sort()->values()->all());
});

it('defines OnboardingStep backed enum cases', function (): void {
    expect(enum_exists(OnboardingStep::class))->toBeTrue()
        ->and(collect(OnboardingStep::cases())->map->value->all())
        ->toBe(onboardingStepValues());
});

it('persists onboarding row with enum casts and steps json', function (): void {
    $onboarding = Onboarding::factory()->create([
        'tenant_slug' => 'acme-corp',
        'correlation_id' => (string) Str::uuid(),
        'state' => OnboardingState::Running,
        'current_step' => OnboardingStep::WaitReadiness,
        'steps' => [
            'provision_tenant' => ['status' => 'completed', 'job_id' => (string) Str::uuid(), 'error' => null],
            'wait_readiness' => ['status' => 'running', 'job_id' => null, 'error' => null],
        ],
        'idempotency_key' => hash('sha256', 'idem-replay-acme'),
        'api_key_id' => null,
    ]);

    $fresh = Onboarding::query()->findOrFail($onboarding->id);
    expect($fresh->state)->toBe(OnboardingState::Running)
        ->and($fresh->current_step)->toBe(OnboardingStep::WaitReadiness)
        ->and($fresh->steps['provision_tenant']['status'])->toBe('completed')
        ->and(strlen($fresh->idempotency_key))->toBe(64);
});

it('creates onboardings table with saga persistence columns', function (): void {
    expect(Schema::hasTable('onboardings'))->toBeTrue();
    $columns = Schema::getColumnListing('onboardings');
    foreach (['id', 'tenant_slug', 'correlation_id', 'state', 'current_step', 'steps', 'idempotency_key', 'api_key_id', 'created_at', 'updated_at'] as $column) {
        expect($columns)->toContain($column);
    }
});
