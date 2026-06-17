<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function makeV1AuthzCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeV1AuthzTenant(string $slug): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => makeV1AuthzCluster()->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function createV1AuthzApiKey(
    ?array $scopes = null,
    ?array $allowedTenantSlugs = null,
): string {
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'V1 AuthZ test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

function v1AuthzBearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function v1UserPayload(): array
{
    return [
        'username' => 'johndoe',
        'password' => 'Secret123!',
        'email' => 'john@acme.com',
    ];
}

function assertV1AuthzForbiddenEnvelope($response): void
{
    $response->assertForbidden();
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
        ],
    ]);
    $response->assertJsonPath('error.code', 'forbidden_scope');
    $lower = strtolower($response->getContent());
    expect($lower)->not->toContain('"subcmd"')
        ->and($lower)->not->toContain('"exit_code"')
        ->and($lower)->not->toContain('"cmd_canonical"');
}

function makeV1AuthzJob(): Job
{
    $cluster = makeV1AuthzCluster();

    Customer::firstOrCreate(['slug' => 'v1-authz-job-co'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'v1-authz-job-co.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'v1-authz-job-co',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'nextcloud-manage v1-authz-job-co _ provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(5),
    ]);
}

it('denies cross-tenant GET /api/v1/tenants/{slug} with DomainError envelope', function () {
    makeV1AuthzTenant('tenant-a');
    makeV1AuthzTenant('tenant-b');
    $rawToken = createV1AuthzApiKey(
        scopes: ['tenants:read'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $response = $this->getJson('/api/v1/tenants/tenant-b', v1AuthzBearer($rawToken));
    assertV1AuthzForbiddenEnvelope($response);
});

it('denies cross-tenant DELETE /api/v1/tenants/{slug} with DomainError envelope', function () {
    makeV1AuthzTenant('tenant-a');
    makeV1AuthzTenant('tenant-b');
    $rawToken = createV1AuthzApiKey(
        scopes: ['tenants:write'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $response = $this->deleteJson(
        '/api/v1/tenants/tenant-b',
        ['confirm_slug' => 'tenant-b'],
        v1AuthzBearer($rawToken),
    );
    assertV1AuthzForbiddenEnvelope($response);
});

it('denies cross-tenant POST /api/v1/tenants/{slug}/users with DomainError envelope', function () {
    makeV1AuthzTenant('tenant-a');
    makeV1AuthzTenant('tenant-b');
    $rawToken = createV1AuthzApiKey(
        scopes: ['users:write'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $response = $this->postJson(
        '/api/v1/tenants/tenant-b/users',
        v1UserPayload(),
        v1AuthzBearer($rawToken),
    );
    assertV1AuthzForbiddenEnvelope($response);
});

it('denies cross-tenant POST /api/v1/tenants/{slug}/apps with DomainError envelope', function () {
    makeV1AuthzTenant('tenant-a');
    makeV1AuthzTenant('tenant-b');
    $rawToken = createV1AuthzApiKey(
        scopes: ['apps:write'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $response = $this->postJson(
        '/api/v1/tenants/tenant-b/apps',
        ['apps' => ['calendar']],
        v1AuthzBearer($rawToken),
    );
    assertV1AuthzForbiddenEnvelope($response);
});

it('denies cross-tenant DELETE /api/v1/tenants/{slug}/users/{username} with DomainError envelope', function () {
    makeV1AuthzTenant('tenant-a');
    makeV1AuthzTenant('tenant-b');
    $rawToken = createV1AuthzApiKey(
        scopes: ['users:write'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $response = $this->deleteJson(
        '/api/v1/tenants/tenant-b/users/alice',
        [],
        v1AuthzBearer($rawToken),
    );
    assertV1AuthzForbiddenEnvelope($response);
});

it('denies cross-tenant POST /api/v1/tenants when allowed_tenant_slugs restricts body slug', function () {
    $cluster = makeV1AuthzCluster();
    $rawToken = createV1AuthzApiKey(
        scopes: ['tenants:write'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'tenant-b',
            'cluster_server_id' => $cluster->id,
            'domain' => 'tenant-b.example.com',
        ],
        v1AuthzBearer($rawToken),
    );
    assertV1AuthzForbiddenEnvelope($response);
});

it('denies bearer key without tenants:write on POST /api/v1/tenants', function () {
    $cluster = makeV1AuthzCluster();
    $rawToken = createV1AuthzApiKey(scopes: ['tenants:read']);

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'new-tenant-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'domain' => 'new.example.com',
        ],
        v1AuthzBearer($rawToken),
    );
    assertV1AuthzForbiddenEnvelope($response);
});

it('denies bearer key without jobs:read on GET /api/v1/jobs/{id}', function () {
    $job = makeV1AuthzJob();
    $rawToken = createV1AuthzApiKey(scopes: ['tenants:read']);

    $response = $this->getJson("/api/v1/jobs/{$job->job_id}", v1AuthzBearer($rawToken));
    assertV1AuthzForbiddenEnvelope($response);
});

it('allows bearer key with jobs:read on GET /api/v1/jobs/{id}', function () {
    $job = makeV1AuthzJob();
    $rawToken = createV1AuthzApiKey(scopes: ['jobs:read']);

    $response = $this->getJson("/api/v1/jobs/{$job->job_id}", v1AuthzBearer($rawToken));

    $response->assertOk();
    $response->assertJsonStructure(['data']);
    $response->assertJsonPath('data.job_id', $job->job_id);
    $lower = strtolower($response->getContent());
    expect($lower)->not->toContain('"subcmd"')
        ->and($lower)->not->toContain('"exit_code"')
        ->and($lower)->not->toContain('"cmd_canonical"');
});
