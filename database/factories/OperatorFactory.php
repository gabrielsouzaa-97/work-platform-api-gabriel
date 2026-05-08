<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => 'operador',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }
}
