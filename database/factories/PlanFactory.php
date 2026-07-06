<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $slug = 'plan-'.substr(uniqid(), -8);

        return [
            'slug' => $slug,
            'name' => ucfirst($slug),
            'description' => null,
            'default_quota' => '5 GB',
            'max_users' => null,
            'is_default' => false,
            'status' => 'active',
        ];
    }

    public function defaultPlan(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
        ]);
    }
}
