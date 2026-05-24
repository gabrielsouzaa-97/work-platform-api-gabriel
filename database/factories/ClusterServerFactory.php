<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClusterServer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClusterServer>
 */
class ClusterServerFactory extends Factory
{
    protected $model = ClusterServer::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'name' => fake()->unique()->word().'-cluster',
            'ssh_host' => fake()->ipv4(),
            'ssh_port' => 22,
            'ssh_user' => 'ncsaas-api',
            'ssh_private_key_encrypted' => 'FAKE_KEY_'.Str::random(8),
            'sftp_user' => 'ncsaas-sftp',
            'sftp_private_key_encrypted' => null,
            'webhook_secret_encrypted' => 'FAKE_SECRET_'.Str::random(16),
            'webhook_secret_version' => 1,
            'schema_version' => 1,
            'status' => 'active',
        ];
    }
}
