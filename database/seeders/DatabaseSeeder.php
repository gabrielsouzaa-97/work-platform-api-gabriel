<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClusterServer;
use App\Models\Operator;
use App\Models\WebhookSecretHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = 'admin@mework360.local';

        if (! Operator::where('email', $adminEmail)->exists()) {
            $admin = Operator::create([
                'id' => Str::uuid()->toString(),
                'email' => $adminEmail,
                'name' => 'Admin',
                'password_hash' => Hash::make('password'),
            ]);

            $admin->role = 'admin';
            $admin->status = 'active';
            $admin->save();
        }

        $devClusterName = 'dev-cluster-local';

        if (! ClusterServer::where('name', $devClusterName)->exists()) {
            $cluster = ClusterServer::create([
                'id' => Str::uuid()->toString(),
                'name' => $devClusterName,
                'ssh_host' => '127.0.0.1',
                'ssh_port' => 22,
                'ssh_user' => 'ncsaas-api',
                'ssh_private_key_encrypted' => 'FAKE_KEY_FOR_DEV_ONLY',
                'webhook_secret_encrypted' => 'FAKE_SECRET_FOR_DEV_ONLY',
                'webhook_secret_version' => 1,
                'schema_version' => 1,
                'status' => 'active',
            ]);

            WebhookSecretHistory::create([
                'cluster_server_id' => $cluster->id,
                'secret_encrypted' => $cluster->webhook_secret_encrypted,
                'version' => 1,
                'valid_from' => now(),
                'valid_until' => null,
            ]);
        }
    }
}
