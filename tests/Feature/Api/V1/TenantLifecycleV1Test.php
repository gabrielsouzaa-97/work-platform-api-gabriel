<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function makeLifecycleV1Cluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeLifecycleV1Tenant(string $slug, string $status = 'active'): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => makeLifecycleV1Cluster()->id,
        'domain' => "{$slug}.example.com",
        'status' => $status,
    ]);
}

function createLifecycleV1ApiKey(
    ?array $scopes = null,
    ?array $allowedTenantSlugs = null,
): string {
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'TenantLifecycle v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

function lifecycleV1Bearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function lifecycleV1UserPayload(): array
{
    return [
        'username' => 'alice',
        'password' => 'Secret123!',
        'email' => 'alice@example.com',
    ];
}

function lifecycleV1SshJobSuccess(string $jobId): SshResponse
{
    return new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    );
}

function mockLifecycleV1SshAsync(string $jobId): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andReturn(lifecycleV1SshJobSuccess($jobId));
    app()->instance(SshClientInterface::class, $ssh);
}

function assertV1AsyncEnvelope(TestResponse $response, string $jobId): void
{
    $response->assertStatus(202);
    $response->assertJsonStructure([
        'data',
        'meta' => [
            'job_id',
            'status_url',
        ],
    ]);
    $response->assertJsonPath('meta.job_id', $jobId);
    expect($response->json('meta.status_url'))->toBeString()->toContain($jobId);
}

function assertV1SyncEnvelope(TestResponse $response): void
{
    $response->assertOk();
    $response->assertJsonStructure(['data']);
    expect($response->json('data'))->toBeArray();
}

function assertLifecycleV1ExcludesNcVocabulary(string $content): void
{
    $lower = strtolower($content);

    expect($lower)->not->toContain('"subcmd"')
        ->and($lower)->not->toContain('"exit_code"')
        ->and($lower)->not->toContain('"cmd_canonical"');
}

it('POST /api/v1/tenants returns 202 with v1 async envelope', function () {
    $cluster = makeLifecycleV1Cluster();
    $jobId = Str::uuid()->toString();
    mockLifecycleV1SshAsync($jobId);
    $rawToken = createLifecycleV1ApiKey(scopes: ['tenants:write']);

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'new-co-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'domain' => 'new-co.example.com',
        ],
        lifecycleV1Bearer($rawToken),
    );

    assertV1AsyncEnvelope($response, $jobId);
    assertLifecycleV1ExcludesNcVocabulary($response->getContent());
});

it('GET /api/v1/tenants/{slug} returns 200 with v1 sync envelope', function () {
    $slug = 'read-v1-'.substr(uniqid(), -6);
    makeLifecycleV1Tenant($slug);
    $rawToken = createLifecycleV1ApiKey(scopes: ['tenants:read'], allowedTenantSlugs: [$slug]);

    $response = $this->getJson(
        "/api/v1/tenants/{$slug}",
        lifecycleV1Bearer($rawToken),
    );

    assertV1SyncEnvelope($response);
    $response->assertJsonPath('data.slug', $slug);
    assertLifecycleV1ExcludesNcVocabulary($response->getContent());
});

it('DELETE /api/v1/tenants/{slug} returns 202 with v1 async envelope', function () {
    $slug = 'del-v1-'.substr(uniqid(), -6);
    makeLifecycleV1Tenant($slug);
    $jobId = Str::uuid()->toString();
    mockLifecycleV1SshAsync($jobId);
    $rawToken = createLifecycleV1ApiKey(scopes: ['tenants:write'], allowedTenantSlugs: [$slug]);

    $response = $this->deleteJson(
        "/api/v1/tenants/{$slug}",
        ['confirm_slug' => $slug],
        lifecycleV1Bearer($rawToken),
    );

    assertV1AsyncEnvelope($response, $jobId);
    assertLifecycleV1ExcludesNcVocabulary($response->getContent());
});

it('POST /api/v1/tenants/{slug}/apps returns 202 with v1 async envelope', function () {
    $slug = 'apps-v1-'.substr(uniqid(), -6);
    makeLifecycleV1Tenant($slug);
    $jobId = Str::uuid()->toString();
    mockLifecycleV1SshAsync($jobId);
    $rawToken = createLifecycleV1ApiKey(scopes: ['apps:write'], allowedTenantSlugs: [$slug]);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/apps",
        ['apps' => ['calendar']],
        lifecycleV1Bearer($rawToken),
    );

    assertV1AsyncEnvelope($response, $jobId);
    assertLifecycleV1ExcludesNcVocabulary($response->getContent());
});

it('POST /api/v1/tenants/{slug}/users on active tenant returns 202 with v1 async envelope', function () {
    $slug = 'users-v1-'.substr(uniqid(), -6);
    makeLifecycleV1Tenant($slug, CustomerLifecycleStatus::ACTIVE);
    $jobId = Str::uuid()->toString();
    mockLifecycleV1SshAsync($jobId);
    $rawToken = createLifecycleV1ApiKey(scopes: ['users:write'], allowedTenantSlugs: [$slug]);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        lifecycleV1UserPayload(),
        lifecycleV1Bearer($rawToken),
    );

    assertV1AsyncEnvelope($response, $jobId);
    assertLifecycleV1ExcludesNcVocabulary($response->getContent());
});

it('DELETE /api/v1/tenants/{slug}/users/{username} returns 202 with v1 async envelope', function () {
    $slug = 'del-user-v1-'.substr(uniqid(), -6);
    makeLifecycleV1Tenant($slug);
    $jobId = Str::uuid()->toString();
    mockLifecycleV1SshAsync($jobId);
    $rawToken = createLifecycleV1ApiKey(scopes: ['users:write'], allowedTenantSlugs: [$slug]);

    $response = $this->deleteJson(
        "/api/v1/tenants/{$slug}/users/alice",
        [],
        lifecycleV1Bearer($rawToken),
    );

    assertV1AsyncEnvelope($response, $jobId);
    assertLifecycleV1ExcludesNcVocabulary($response->getContent());
});

it('GET /api/v1/jobs/{id} returns 200 v1 sync envelope without NC fields', function () {
    $cluster = makeLifecycleV1Cluster();
    $slug = 'job-v1-'.substr(uniqid(), -6);
    Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
    $job = Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => "nextcloud-manage {$slug} _ provision",
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(5),
    ]);
    $rawToken = createLifecycleV1ApiKey(scopes: ['jobs:read']);

    $response = $this->getJson(
        "/api/v1/jobs/{$job->job_id}",
        lifecycleV1Bearer($rawToken),
    );

    assertV1SyncEnvelope($response);
    $response->assertJsonPath('data.job_id', $job->job_id);
    assertLifecycleV1ExcludesNcVocabulary($response->getContent());
});

it('blocked v1 onboarding returns not_implemented without NC vocabulary', function () {
    $slug = 'blocked-v1-'.substr(uniqid(), -6);
    makeLifecycleV1Tenant($slug);
    $rawToken = createLifecycleV1ApiKey(scopes: ['onboarding:run'], allowedTenantSlugs: [$slug]);

    $onboarding = $this->postJson(
        '/api/v1/onboarding',
        ['slug' => $slug],
        lifecycleV1Bearer($rawToken),
    );
    $onboarding->assertStatus(501);
    $onboarding->assertJsonPath('error.code', 'not_implemented');
    assertLifecycleV1ExcludesNcVocabulary($onboarding->getContent());
});
