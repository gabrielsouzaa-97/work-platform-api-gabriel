<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function makeQuotaV1Cluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeQuotaV1Tenant(string $slug, ClusterServer $cluster): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function createQuotaV1ApiKey(?array $allowedTenantSlugs = null): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Quota v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['users:write'],
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

function quotaV1Bearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

it('PUT v1 user quota applies via platform port with v1 envelope', function (): void {
    $cluster = makeQuotaV1Cluster();
    $slug = 'quota-v1-'.substr(uniqid(), -6);
    makeQuotaV1Tenant($slug, $cluster);
    $rawToken = createQuotaV1ApiKey([$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['result' => 'ok']),
            stderr: '',
            exitCode: 0,
            parsedJson: ['result' => 'ok'],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->putJson(
        "/api/v1/tenants/{$slug}/users/alice/quota",
        ['quota' => '5GB'],
        quotaV1Bearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonPath('data.result', 'ok');
    expect(AuditLog::where('action', 'v1_set_user_quota')->where('resource_id', $slug)->exists())->toBeTrue();
});

it('PUT v1 user quota maps OCC allowlist rejection to capability_not_available', function (): void {
    $cluster = makeQuotaV1Cluster();
    $slug = 'quota-blocked-'.substr(uniqid(), -6);
    makeQuotaV1Tenant($slug, $cluster);
    $rawToken = createQuotaV1ApiKey([$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andThrow(new SshRemoteException('blocked', 16));
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->putJson(
        "/api/v1/tenants/{$slug}/users/alice/quota",
        ['quota' => '5GB'],
        quotaV1Bearer($rawToken),
    );

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'capability_not_available');
    $response->assertJsonMissingPath('error.subcmd');
    $response->assertJsonMissingPath('error.exit_code');
});

it('PUT v1 user quota returns tenant_not_found for unknown slug', function (): void {
    $rawToken = createQuotaV1ApiKey(['missing-tenant']);

    $response = $this->putJson(
        '/api/v1/tenants/missing-tenant/users/alice/quota',
        ['quota' => '5GB'],
        quotaV1Bearer($rawToken),
    );

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'tenant_not_found');
});
