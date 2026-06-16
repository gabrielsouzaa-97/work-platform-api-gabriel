<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClusterServer>
 */
class ClusterServerFactory extends Factory
{
    protected $model = ClusterServer::class;

    public function withoutWebhookHistory(): static
    {
        return $this->afterCreating(function (ClusterServer $cluster): void {
            WebhookSecretHistory::where('cluster_server_id', $cluster->id)->delete();
        });
    }

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'name' => fake()->unique()->word().'-cluster',
            'ssh_host' => fake()->ipv4(),
            'ssh_port' => 22,
            'ssh_user' => 'ncsaas-api',
            'sftp_user' => 'ncsaas-sftp',
            'sftp_private_key_encrypted' => null,
            'webhook_secret_version' => 1,
            'schema_version' => 1,
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ClusterServer $cluster): void {
            if ($cluster->ssh_private_key_encrypted === null || $cluster->ssh_private_key_encrypted === '') {
                $cluster->ssh_private_key_encrypted = 'FAKE_KEY_'.Str::random(8);
            }

            if ($cluster->webhook_secret_encrypted === null || $cluster->webhook_secret_encrypted === '') {
                $cluster->webhook_secret_encrypted = 'FAKE_SECRET_'.Str::random(16);
            }
        })->afterCreating(function (ClusterServer $cluster): void {
            if (WebhookSecretHistory::where('cluster_server_id', $cluster->id)->exists()) {
                return;
            }

            WebhookSecretHistory::createWithSecret([
                'cluster_server_id' => $cluster->id,
                'version' => $cluster->webhook_secret_version ?? 1,
                'valid_from' => now()->subHour(),
                'valid_until' => null,
            ], (string) $cluster->webhook_secret_encrypted);
        });
    }
}
