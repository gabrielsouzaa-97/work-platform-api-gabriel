<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

function makeAuthzCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeAuthzCustomer(string $slug): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => makeAuthzCluster()->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function createApiKeyWithToken(
    ?array $scopes = null,
    ?array $allowedTenantSlugs = null,
    string $name = 'AuthZ test key',
): string {
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => $name,
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

function bearerHeaders(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function createUserPayload(): array
{
    return [
        'username' => 'johndoe',
        'password' => 'Secret123!',
        'email' => 'john@acme.com',
    ];
}

function makeAuthzJob(): Job
{
    $cluster = makeAuthzCluster();

    Customer::firstOrCreate(['slug' => 'authz-job-co'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'authz-job-co.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'authz-job-co',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'nextcloud-manage authz-job-co _ provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(5),
    ]);
}

it('allows unrestricted internal bearer key on GET /api/queue', function () {
    $rawToken = createApiKeyWithToken(scopes: null);

    $this->getJson('/api/queue', bearerHeaders($rawToken))
        ->assertOk();
});

it('allows bearer key with queue:read scope on GET /api/queue', function () {
    $rawToken = createApiKeyWithToken(scopes: ['queue:read']);

    $this->getJson('/api/queue', bearerHeaders($rawToken))
        ->assertOk();
});

it('denies bearer key without queue scope on GET /api/queue', function () {
    $rawToken = createApiKeyWithToken(scopes: ['customers:write']);

    $this->getJson('/api/queue', bearerHeaders($rawToken))
        ->assertForbidden()
        ->assertJsonPath('error', 'forbidden_scope');
});

it('denies bearer key with only queue:read on POST /api/queue/{id}/cancel', function () {
    $job = makeAuthzJob();
    $rawToken = createApiKeyWithToken(scopes: ['queue:read']);

    $this->postJson("/api/queue/{$job->job_id}/cancel", [], bearerHeaders($rawToken))
        ->assertForbidden()
        ->assertJsonPath('error', 'forbidden_scope');
});

it('denies cross-tenant DELETE when allowed_tenant_slugs restricts slug', function () {
    makeAuthzCustomer('tenant-a');
    makeAuthzCustomer('tenant-b');
    $rawToken = createApiKeyWithToken(
        scopes: ['customers:write'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $this->deleteJson(
        '/api/customers/tenant-b',
        ['confirm_slug' => 'tenant-b'],
        bearerHeaders($rawToken),
    )
        ->assertForbidden()
        ->assertJsonPath('error', 'forbidden_tenant');
});

it('denies cross-tenant POST /api/customers when allowed_tenant_slugs restricts slug', function () {
    $cluster = makeAuthzCluster();
    $rawToken = createApiKeyWithToken(
        scopes: ['customers:write'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $this->postJson(
        '/api/customers',
        [
            'slug' => 'tenant-b',
            'cluster_server_id' => $cluster->id,
            'domain' => 'tenant-b.example.com',
        ],
        bearerHeaders($rawToken),
    )
        ->assertForbidden()
        ->assertJsonPath('error', 'forbidden_tenant');
});

it('denies cross-tenant POST when allowed_tenant_slugs restricts slug', function () {
    makeAuthzCustomer('tenant-a');
    makeAuthzCustomer('tenant-b');
    $rawToken = createApiKeyWithToken(
        scopes: null,
        allowedTenantSlugs: ['tenant-a'],
    );

    $this->postJson(
        '/api/customers/tenant-b/users',
        createUserPayload(),
        bearerHeaders($rawToken),
    )
        ->assertForbidden()
        ->assertJsonPath('error', 'forbidden_tenant');
});

it('passes authz for allowed tenant and lifecycle:write scope on POST users', function () {
    makeAuthzCustomer('tenant-a');
    $rawToken = createApiKeyWithToken(
        scopes: ['lifecycle:write'],
        allowedTenantSlugs: ['tenant-a'],
    );

    $response = $this->postJson(
        '/api/customers/tenant-a/users',
        createUserPayload(),
        bearerHeaders($rawToken),
    );

    expect($response->json('error'))->not->toBeIn(['forbidden_scope', 'forbidden_tenant']);
});

it('records api_key_id on AuditLog when bearer key cancels a job', function () {
    $job = makeAuthzJob();
    $rawToken = createApiKeyWithToken(scopes: ['queue:write']);
    $apiKey = ApiKey::where('token_hash', hash('sha256', $rawToken))->firstOrFail();

    $this->mock(SshClientInterface::class, function ($mock): void {
        $mock->shouldReceive('run')
            ->once()
            ->andReturn(new SshResponse(
                stdout: '{"status":"cancelled"}',
                stderr: '',
                exitCode: 0,
                parsedJson: ['status' => 'cancelled'],
            ));
    });

    $this->postJson("/api/queue/{$job->job_id}/cancel", [], bearerHeaders($rawToken))
        ->assertNoContent();

    $audit = AuditLog::where('action', 'job.cancel')
        ->where('job_id', $job->job_id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->api_key_id)->toBe($apiKey->id);
});
