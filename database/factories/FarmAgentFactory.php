<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClusterServer;
use App\Models\FarmAgent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FarmAgent>
 */
class FarmAgentFactory extends Factory
{
    protected $model = FarmAgent::class;

    public function definition(): array
    {
        return [
            'farm_id' => 'farm-'.Str::lower(Str::random(8)),
            'cluster_server_id' => ClusterServer::factory(),
            'agent_token_hash' => hash('sha256', 'test-agent-token'),
            'mtls_cert_fingerprint' => null,
            'status' => 'active',
            'last_seen_at' => now(),
        ];
    }

    public function offline(): static
    {
        return $this->state(fn (): array => [
            'last_seen_at' => now()->subHours(2),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => 'revoked',
        ]);
    }
}
