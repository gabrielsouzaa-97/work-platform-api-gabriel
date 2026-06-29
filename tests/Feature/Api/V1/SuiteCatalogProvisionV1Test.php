<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    config([
        'platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog.json'),
        'platform.suite_catalog.default_mode' => true,
    ]);
});

function suiteCatalogV1ApiKey(): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'SuiteCatalog v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:write'],
        'allowed_tenant_slugs' => null,
    ]);

    return $rawToken;
}

function suiteCatalogV1Bearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function mockSuiteCatalogSsh(string $jobId, ?callable $assertArgs = null, ?callable $assertStdin = null): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (
            ClusterServer $cluster,
            string $cmd,
            array $args,
            ?string $payloadStdin = null,
        ) use ($assertArgs, $assertStdin): bool {
            if ($cluster->status !== 'active' || $cmd !== 'nextcloud-manage') {
                return false;
            }

            if ($assertArgs !== null && ! $assertArgs($args)) {
                return false;
            }

            if ($assertStdin !== null && ! $assertStdin($payloadStdin)) {
                return false;
            }

            return true;
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);
}

it('POST /api/v1/tenants with suite catalog apps dispatches --suite-catalog and --apps', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 'suite-'.substr(uniqid(), -6);

    mockSuiteCatalogSsh(
        $jobId,
        fn (array $args): bool => in_array('--suite-catalog', $args, true)
            && in_array('--apps=mail,dashboard,spreed', $args, true)
            && in_array('--payload-stdin', $args, true),
        fn (?string $stdin): bool => is_string($stdin)
            && ($decoded = json_decode($stdin, true))
            && is_array($decoded)
            && ($decoded['shell'] ?? null) === true,
    );

    $rawToken = suiteCatalogV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'client' => $slug,
            'fqdn' => 'cloud.labwork.mework360.com.br',
            'cluster_server_id' => $cluster->id,
            'shell' => true,
            'apps' => ['mail', 'dashboard', 'spreed'],
        ],
        suiteCatalogV1Bearer($rawToken),
    );

    $response->assertStatus(202);
    $response->assertJsonPath('meta.job_id', $jobId);
});

it('POST /api/v1/tenants rejects unknown suite catalog app_id with 422', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $rawToken = suiteCatalogV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'bad-app-'.substr(uniqid(), -6),
            'domain' => 'bad.example.com',
            'cluster_server_id' => $cluster->id,
            'apps' => ['not-a-real-app'],
        ],
        suiteCatalogV1Bearer($rawToken),
    );

    $response->assertStatus(422);
});

it('POST /api/v1/tenants rejects planned suite catalog app with 422', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $rawToken = suiteCatalogV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'planned-'.substr(uniqid(), -6),
            'domain' => 'planned.example.com',
            'cluster_server_id' => $cluster->id,
            'apps' => ['richdocuments'],
        ],
        suiteCatalogV1Bearer($rawToken),
    );

    $response->assertStatus(422);
});

it('POST /api/v1/tenants with shell false sends stdin shell false', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 'noshell-'.substr(uniqid(), -6);

    mockSuiteCatalogSsh(
        $jobId,
        assertStdin: fn (?string $stdin): bool => is_string($stdin)
            && ($decoded = json_decode($stdin, true))
            && is_array($decoded)
            && array_key_exists('shell', $decoded)
            && $decoded['shell'] === false,
    );

    $rawToken = suiteCatalogV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 'noshell.example.com',
            'cluster_server_id' => $cluster->id,
            'shell' => false,
            'apps' => ['mail'],
        ],
        suiteCatalogV1Bearer($rawToken),
    );

    $response->assertStatus(202);
});
