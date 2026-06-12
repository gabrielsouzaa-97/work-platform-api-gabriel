<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    private const PRIVILEGED_ATTRIBUTES = ['role', 'status', 'invite_token_hash'];

    protected $model = Operator::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'password_hash' => Hash::make('password'),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Operator $operator): void {
            $operator->role ??= 'operador';
            $operator->status ??= 'active';
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null): Operator
    {
        $privileged = $this->extractPrivilegedAttributes($attributes);

        /** @var Operator $operator */
        $operator = parent::create($attributes, $parent);

        return $this->applyPrivilegedAttributes($operator, $privileged);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function make($attributes = [], ?Model $parent = null): Operator
    {
        $privileged = $this->extractPrivilegedAttributes($attributes);

        /** @var Operator $operator */
        $operator = parent::make($attributes, $parent);

        return $this->applyPrivilegedAttributes($operator, $privileged, persist: false);
    }

    public function admin(): static
    {
        return $this->afterMaking(function (Operator $operator): void {
            $operator->role = 'admin';
        });
    }

    public function inactive(): static
    {
        return $this->afterMaking(function (Operator $operator): void {
            $operator->status = 'inactive';
        });
    }

    public function pending(): static
    {
        return $this->afterMaking(function (Operator $operator): void {
            $operator->status = 'pending';
        });
    }

    public function invited(string $token = 'valid-invite-token'): static
    {
        return $this->pending()
            ->afterMaking(function (Operator $operator) use ($token): void {
                $operator->invite_token_hash = Hash::make($token);
                $operator->invite_expires_at = now()->addHours(48);
            });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function extractPrivilegedAttributes(array &$attributes): array
    {
        $privileged = [];

        foreach (self::PRIVILEGED_ATTRIBUTES as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $privileged[$key] = $attributes[$key];
            unset($attributes[$key]);
        }

        return $privileged;
    }

    /**
     * @param  array<string, mixed>  $privileged
     */
    private function applyPrivilegedAttributes(Operator $operator, array $privileged, bool $persist = true): Operator
    {
        if ($privileged === []) {
            return $operator;
        }

        foreach ($privileged as $key => $value) {
            $operator->{$key} = $value;
        }

        if ($persist && $operator->exists) {
            $operator->save();
        }

        return $operator;
    }
}
