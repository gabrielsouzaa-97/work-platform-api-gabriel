<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function tenantDomainV1ApiKey(): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Tenant domain v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:write'],
        'allowed_tenant_slugs' => null,
    ]);

    return $rawToken;
}

function tenantDomainV1Bearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function mockTenantDomainV1Provision(string $jobId): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);
}

function mockTenantDomainV1ProvisionBlocked(): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);
}

it('POST /api/v1/tenants normalizes fqdn with trailing slash via ProvisionTenantRequest', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 'v1-norm-'.substr(uniqid(), -6);
    $expected = 'pacoteste.image-pilot.mework360.com.br';

    mockTenantDomainV1Provision($jobId);
    $rawToken = tenantDomainV1ApiKey();

    $response = test()->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'cluster_server_id' => $cluster->id,
            'fqdn' => 'Pacoteste.Image-Pilot.MeWork360.Com.Br/',
        ],
        tenantDomainV1Bearer($rawToken),
    );

    $response->assertStatus(202);
    expect(Customer::find($slug)?->domain)->toBe($expected);
});

it('POST /api/v1/tenants rejects fqdn with protocol with 422', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    mockTenantDomainV1ProvisionBlocked();
    $rawToken = tenantDomainV1ApiKey();

    $response = test()->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'v1-proto-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'fqdn' => 'https://foo.bar',
        ],
        tenantDomainV1Bearer($rawToken),
    );

    $response->assertStatus(422);
    expect(array_intersect(
        array_keys($response->json('errors', [])),
        ['domain', 'fqdn'],
    ))->not->toBeEmpty();
});

it('POST /api/v1/tenants rejects fqdn without TLD with 422', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    mockTenantDomainV1ProvisionBlocked();
    $rawToken = tenantDomainV1ApiKey();

    $response = test()->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'v1-notld-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'fqdn' => 'foo',
        ],
        tenantDomainV1Bearer($rawToken),
    );

    $response->assertStatus(422);
    expect(array_intersect(
        array_keys($response->json('errors', [])),
        ['domain', 'fqdn'],
    ))->not->toBeEmpty();
});
