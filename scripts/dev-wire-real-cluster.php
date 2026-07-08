<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;
use App\Modules\ClusterServers\Services\WebhookSecretGenerator;
use App\Modules\Integration\Dto\ProbeClusterHealthCommand;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Contracts\Console\Kernel;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$clusterId = getenv('DEV_CLUSTER_ID') ?: '65ca7a34-1231-4983-a1c6-c78807631922';
$sshHost = getenv('DEV_CLUSTER_SSH_HOST') ?: '128.201.61.112';
$sshPort = (int) (getenv('DEV_CLUSTER_SSH_PORT') ?: 22);
$sshUser = getenv('DEV_CLUSTER_SSH_USER') ?: 'ncsaas-api';
$keyPath = getenv('DEV_CLUSTER_SSH_KEY_PATH') ?: storage_path('app/dev-cluster-ssh/id_ed25519');
$pubPath = $keyPath.'.pub';

$cluster = ClusterServer::find($clusterId);
if ($cluster === null) {
    fwrite(STDERR, "Cluster not found: {$clusterId}\n");
    exit(1);
}

if (! is_file($keyPath)) {
    $dir = dirname($keyPath);
    if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
        fwrite(STDERR, "Failed to create key directory: {$dir}\n");
        exit(1);
    }

    $key = EC::createKey('Ed25519');
    $pem = $key->toString('OpenSSH');
    $pub = $key->getPublicKey()->toString('OpenSSH');
    if (file_put_contents($keyPath, $pem."\n") === false) {
        fwrite(STDERR, "Failed to write private key: {$keyPath}\n");
        exit(1);
    }
    if (file_put_contents($pubPath, $pub."\n") === false) {
        fwrite(STDERR, "Failed to write public key: {$pubPath}\n");
        exit(1);
    }
    chmod($keyPath, 0600);
    chmod($pubPath, 0644);
}

$pem = file_get_contents($keyPath);
if ($pem === false || trim($pem) === '') {
    fwrite(STDERR, "Empty private key at {$keyPath}\n");
    exit(1);
}

try {
    PublicKeyLoader::load($pem);
} catch (Throwable $e) {
    fwrite(STDERR, "Invalid private key PEM: {$e->getMessage()}\n");
    exit(1);
}

$plainWebhookSecret = WebhookSecretGenerator::generate();

$cluster->name = getenv('DEV_CLUSTER_NAME') ?: 'labwork';
$cluster->ssh_host = $sshHost;
$cluster->ssh_port = $sshPort;
$cluster->ssh_user = $sshUser;
$cluster->ssh_private_key_encrypted = $pem;
$cluster->webhook_secret_encrypted = $plainWebhookSecret;
$cluster->webhook_secret_version = max(1, (int) $cluster->webhook_secret_version);
$cluster->webhook_allowed_ip = $sshHost;
$cluster->nextcloud_version = $cluster->nextcloud_version ?: '33.0.5';
$cluster->schema_version = max(1, (int) $cluster->schema_version);
$cluster->status = 'active';
$cluster->save();

WebhookSecretHistory::query()
    ->where('cluster_server_id', $cluster->id)
    ->whereNull('valid_until')
    ->update(['valid_until' => now()]);

WebhookSecretHistory::createWithSecret([
    'cluster_server_id' => $cluster->id,
    'version' => $cluster->webhook_secret_version,
    'valid_from' => now(),
    'valid_until' => null,
], $plainWebhookSecret);

echo "Cluster wired: {$cluster->id} ({$cluster->name})\n";
echo "SSH target: {$sshUser}@{$sshHost}:{$sshPort}\n";
echo "Private key: {$keyPath}\n";

if (is_file($pubPath)) {
    echo "\n--- PUBLIC KEY (authorize on cluster for ncsaas-api) ---\n";
    echo trim((string) file_get_contents($pubPath))."\n";
    echo "--- END PUBLIC KEY ---\n";
}

echo "\nWebhook secret (configure on cluster upstream): {$plainWebhookSecret}\n";
echo "Webhook callback template: {APP_URL}/api/jobs/hook?cluster={$cluster->id}\n";

/** @var PlatformPortFactory $factory */
$factory = app(PlatformPortFactory::class);

try {
    $report = $factory->for($cluster)->probeClusterHealth(
        new ProbeClusterHealthCommand($cluster, 15),
    );
    $ok = $report->exitCode === 0;
    echo "\nHealth probe: ".($ok ? 'OK (exit 0)' : "FAIL (exit {$report->exitCode})")."\n";
    if (! $ok) {
        exit(2);
    }
} catch (Throwable $e) {
    echo "\nHealth probe: FAIL ({$e->getMessage()})\n";
    echo "Authorize the public key above on {$sshHost} then rerun:\n";
    echo "  docker compose exec app php scripts/dev-wire-real-cluster.php\n";
    exit(2);
}

exit(0);
