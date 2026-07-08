<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\TenantUser;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function makeUsersV1Cluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeUsersV1Tenant(string $slug, ClusterServer $cluster): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function createUsersV1ApiKey(array $scopes, ?array $allowedTenantSlugs = null): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Users v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

function usersV1Bearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

it('GET v1 tenant users returns tenant_users projection with v1 envelope', function (): void {
    $cluster = makeUsersV1Cluster();
    $slug = 'users-v1-'.substr(uniqid(), -6);
    makeUsersV1Tenant($slug, $cluster);
    $rawToken = createUsersV1ApiKey(['users:read'], [$slug]);

    TenantUser::create([
        'customer_slug' => $slug,
        'username' => 'alice',
        'email' => 'alice@example.com',
        'quota' => '5 GB',
        'groups' => ['admin'],
        'origin' => 'panel',
    ]);
    TenantUser::create([
        'customer_slug' => $slug,
        'username' => 'bob',
        'email' => 'bob@example.com',
        'quota' => null,
        'groups' => [],
        'origin' => 'panel',
    ]);

    $response = $this->getJson(
        "/api/v1/tenants/{$slug}/users",
        usersV1Bearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonPath('data.users.0.username', 'alice');
    $response->assertJsonPath('data.users.0.email', 'alice@example.com');
    $response->assertJsonPath('data.users.0.quota', '5 GB');
    $response->assertJsonPath('data.users.0.groups', ['admin']);
    $response->assertJsonPath('data.users.1.username', 'bob');
    $response->assertJsonCount(2, 'data.users');
});

it('GET v1 tenant users returns tenant_not_found for unknown slug', function (): void {
    $rawToken = createUsersV1ApiKey(['users:read'], ['missing-tenant']);

    $response = $this->getJson(
        '/api/v1/tenants/missing-tenant/users',
        usersV1Bearer($rawToken),
    );

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'tenant_not_found');
});

it('POST v1 maintenance toggles via platform port with v1 envelope', function (): void {
    $cluster = makeUsersV1Cluster();
    $slug = 'maint-v1-'.substr(uniqid(), -6);
    makeUsersV1Tenant($slug, $cluster);
    $rawToken = createUsersV1ApiKey(['maintenance:write'], [$slug]);

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

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/maintenance",
        ['on' => true],
        usersV1Bearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonPath('data.result', 'ok');
    expect(AuditLog::where('action', 'v1_toggle_maintenance')->where('resource_id', $slug)->exists())->toBeTrue();
});

it('POST v1 maintenance maps OCC allowlist rejection to capability_not_available', function (): void {
    $cluster = makeUsersV1Cluster();
    $slug = 'maint-blocked-'.substr(uniqid(), -6);
    makeUsersV1Tenant($slug, $cluster);
    $rawToken = createUsersV1ApiKey(['maintenance:write'], [$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andThrow(new SshRemoteException('blocked', 16));
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/maintenance",
        ['on' => false],
        usersV1Bearer($rawToken),
    );

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'capability_not_available');
});

it('POST v1 maintenance requires on field', function (): void {
    $cluster = makeUsersV1Cluster();
    $slug = 'maint-422-'.substr(uniqid(), -6);
    makeUsersV1Tenant($slug, $cluster);
    $rawToken = createUsersV1ApiKey(['maintenance:write'], [$slug]);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/maintenance",
        [],
        usersV1Bearer($rawToken),
    );

    $response->assertUnprocessable();
});
