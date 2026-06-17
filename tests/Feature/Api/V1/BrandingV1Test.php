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

function makeBrandingV1Cluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeBrandingV1Tenant(string $slug, ClusterServer $cluster): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function createBrandingV1ApiKey(?array $allowedTenantSlugs = null): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Branding v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['branding:write'],
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

function brandingV1Bearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function sshBrandingSuccess(array $data = []): SshResponse
{
    return new SshResponse(
        stdout: json_encode($data ?: ['result' => 'ok']),
        stderr: '',
        exitCode: 0,
        parsedJson: $data ?: ['result' => 'ok'],
    );
}

it('PUT v1 branding applies theming via platform port with v1 envelope', function (): void {
    $cluster = makeBrandingV1Cluster();
    $slug = 'brand-v1-'.substr(uniqid(), -6);
    makeBrandingV1Tenant($slug, $cluster);
    $rawToken = createBrandingV1ApiKey([$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->twice()
        ->withArgs(function ($c, $cmd, $args) {
            return $cmd === 'nextcloud-manage' && in_array('theming:config', $args, true);
        })
        ->andReturn(sshBrandingSuccess());
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->putJson(
        "/api/v1/tenants/{$slug}/branding",
        ['name' => 'Acme Corp', 'color' => '#123456'],
        brandingV1Bearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonPath('data.result', 'ok');
    expect(AuditLog::where('action', 'v1_set_branding')->where('resource_id', $slug)->exists())->toBeTrue();
});

it('PUT v1 branding maps OCC allowlist rejection to capability_not_available', function (): void {
    $cluster = makeBrandingV1Cluster();
    $slug = 'brand-blocked-'.substr(uniqid(), -6);
    makeBrandingV1Tenant($slug, $cluster);
    $rawToken = createBrandingV1ApiKey([$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andThrow(new SshRemoteException('blocked', 16));
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->putJson(
        "/api/v1/tenants/{$slug}/branding",
        ['name' => 'Acme Corp'],
        brandingV1Bearer($rawToken),
    );

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'capability_not_available');
});

it('PUT v1 branding returns tenant_not_found for unknown slug', function (): void {
    $rawToken = createBrandingV1ApiKey(['missing-tenant']);

    $response = $this->putJson(
        '/api/v1/tenants/missing-tenant/branding',
        ['name' => 'Acme Corp'],
        brandingV1Bearer($rawToken),
    );

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'tenant_not_found');
});
