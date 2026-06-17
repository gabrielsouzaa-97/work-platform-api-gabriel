<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        $rawToken = 'sk_'.bin2hex(random_bytes(32));

        return [
            'id' => Str::uuid()->toString(),
            'operator_id' => null,
            'name' => fake()->words(3, true),
            'token_hash' => hash('sha256', $rawToken),
            'scopes' => null,
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()->subDay()]);
    }

    public function withScope(string ...$scopes): static
    {
        return $this->state(['scopes' => $scopes]);
    }

    public function withAllowedTenants(string ...$slugs): static
    {
        return $this->state(['allowed_tenant_slugs' => $slugs]);
    }
}
