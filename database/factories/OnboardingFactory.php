<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Onboarding;
use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Onboarding>
 */
class OnboardingFactory extends Factory
{
    protected $model = Onboarding::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'tenant_slug' => fake()->unique()->slug(2),
            'correlation_id' => Str::uuid()->toString(),
            'state' => OnboardingState::Pending,
            'current_step' => OnboardingStep::ProvisionTenant,
            'steps' => [],
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'api_key_id' => null,
        ];
    }
}
