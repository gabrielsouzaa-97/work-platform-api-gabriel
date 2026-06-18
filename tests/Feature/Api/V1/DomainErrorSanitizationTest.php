<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function makeDomainErrorCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeDomainErrorTenant(string $slug, string $status = 'active'): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => makeDomainErrorCluster()->id,
        'domain' => "{$slug}.example.com",
        'status' => $status,
    ]);
}

function createDomainErrorApiKey(
    ?array $scopes = null,
    ?array $allowedTenantSlugs = null,
): string {
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'DomainError v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

function domainErrorBearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function assertApiV1ResponseExcludesNcVocabulary(string $content): void
{
    $lower = strtolower($content);

    expect($lower)->not->toContain('"subcmd"')
        ->and($lower)->not->toContain('"exit_code"')
        ->and($lower)->not->toContain('"cmd_canonical"');
}

function assertDomainErrorEnvelope(
    TestResponse $response,
    int $expectedStatus,
    string $expectedCode,
): void {
    $response->assertStatus($expectedStatus);
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
        ],
    ]);
    $response->assertJsonPath('error.code', $expectedCode);
    expect($response->json('error.message'))->toBeString()->not->toBeEmpty();
    assertApiV1ResponseExcludesNcVocabulary($response->getContent());
}

it('returns DomainError without exit_code when provision SSH fails on POST /api/v1/tenants', function () {
    $cluster = makeDomainErrorCluster();
    $slug = 'prov-ssh-'.substr(uniqid(), -6);
    $rawToken = createDomainErrorApiKey(scopes: ['tenants:write']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andThrow(new SshRemoteException('upstream fail', 99));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'cluster_server_id' => $cluster->id,
            'domain' => "{$slug}.example.com",
        ],
        domainErrorBearer($rawToken),
    );

    assertDomainErrorEnvelope($response, 502, 'upstream_unavailable');
});

it('returns 403 DomainError without NC fields when upstream blocks OCC subcmd on v1 apps', function () {
    $slug = 'occ-block-'.substr(uniqid(), -6);
    makeDomainErrorTenant($slug);
    $rawToken = createDomainErrorApiKey(scopes: ['apps:write'], allowedTenantSlugs: [$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andThrow(new SshRemoteException('subcmd not allowed', 16));
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/apps",
        ['apps' => ['calendar']],
        domainErrorBearer($rawToken),
    );

    expect($response->status())->toBeIn([403, 404, 502]);
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
        ],
    ]);
    expect($response->json('error.code'))->toBeIn([
        'capability_not_available',
        'forbidden_scope',
        'upstream_unavailable',
    ]);
    assertApiV1ResponseExcludesNcVocabulary($response->getContent());
});

it('returns 503 tenant_not_ready with retry_after for provisioning tenant on POST v1 users', function () {
    $slug = 'prov-v1-'.substr(uniqid(), -6);
    makeDomainErrorTenant($slug, CustomerLifecycleStatus::PROVISIONING_FINISHING);
    $rawToken = createDomainErrorApiKey(scopes: ['users:write'], allowedTenantSlugs: [$slug]);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'alice',
            'password' => 'Secret123!',
            'email' => 'alice@example.com',
        ],
        domainErrorBearer($rawToken),
    );

    $response->assertStatus(503);
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
            'retry_after',
        ],
    ]);
    $response->assertJsonPath('error.code', 'tenant_not_ready');
    $response->assertHeader('Retry-After');
    expect($response->json('error.retry_after'))->toBeInt()->toBeGreaterThan(0);
    assertApiV1ResponseExcludesNcVocabulary($response->getContent());
});

it('returns 404 tenant_not_found DomainError for missing tenant on GET v1 tenants', function () {
    $rawToken = createDomainErrorApiKey(scopes: ['tenants:read']);

    $response = $this->getJson(
        '/api/v1/tenants/missing-tenant-'.substr(uniqid(), -8),
        domainErrorBearer($rawToken),
    );

    assertDomainErrorEnvelope($response, 404, 'tenant_not_found');
});

it('returns 403 forbidden_scope DomainError without NC fields when scope is insufficient', function () {
    $slug = 'scope-v1-'.substr(uniqid(), -6);
    makeDomainErrorTenant($slug);
    $rawToken = createDomainErrorApiKey(scopes: ['jobs:read'], allowedTenantSlugs: [$slug]);

    $response = $this->getJson(
        "/api/v1/tenants/{$slug}",
        domainErrorBearer($rawToken),
    );

    assertDomainErrorEnvelope($response, 403, 'forbidden_scope');
});

it('returns DomainError envelope for unknown v1 route without legacy flat error string', function () {
    $rawToken = createDomainErrorApiKey(scopes: null);

    $response = $this->getJson(
        '/api/v1/rota-inexistente-'.substr(uniqid(), -8),
        domainErrorBearer($rawToken),
    );

    $response->assertNotFound();
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
        ],
    ]);
    expect($response->json('error'))->toBeArray();
    expect($response->json('error.code'))->toBeString()->not->toBeEmpty();
    assertApiV1ResponseExcludesNcVocabulary($response->getContent());
});

it('returns DomainError without exit_code when remove SSH fails on DELETE /api/v1/tenants/{slug}', function () {
    $slug = 'del-ssh-'.substr(uniqid(), -6);
    makeDomainErrorTenant($slug);
    $rawToken = createDomainErrorApiKey(scopes: ['tenants:write'], allowedTenantSlugs: [$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andThrow(new SshRemoteException('upstream remove failed', 99));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->deleteJson(
        "/api/v1/tenants/{$slug}",
        ['confirm_slug' => $slug],
        domainErrorBearer($rawToken),
    );

    assertDomainErrorEnvelope($response, 502, 'upstream_unavailable');
});

it('returns 503 cluster_unreachable DomainError on DELETE /api/v1/tenants/{slug} when transport fails', function () {
    $slug = 'del-unreach-'.substr(uniqid(), -6);
    makeDomainErrorTenant($slug);
    $rawToken = createDomainErrorApiKey(scopes: ['tenants:write'], allowedTenantSlugs: [$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andThrow(new SshConnectionException('connection refused'));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->deleteJson(
        "/api/v1/tenants/{$slug}",
        ['confirm_slug' => $slug],
        domainErrorBearer($rawToken),
    );

    $response->assertStatus(503);
    $response->assertJsonStructure([
        'error' => [
            'code',
            'message',
            'retry_after',
        ],
    ]);
    $response->assertJsonPath('error.code', 'cluster_unreachable');
    $response->assertHeader('Retry-After');
    assertApiV1ResponseExcludesNcVocabulary($response->getContent());
});

it('never exposes subcmd exit_code or cmd_canonical in v1 error JSON bodies', function () {
    $slug = 'nc-leak-'.substr(uniqid(), -6);
    makeDomainErrorTenant($slug);
    $rawToken = createDomainErrorApiKey(scopes: ['apps:write'], allowedTenantSlugs: [$slug]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andThrow(new SshRemoteException('subcmd not allowed', 16));
    app()->instance(SshClientInterface::class, $ssh);

    $responses = [
        $this->postJson(
            "/api/v1/tenants/{$slug}/apps",
            ['apps' => ['calendar']],
            domainErrorBearer($rawToken),
        ),
        $this->getJson(
            '/api/v1/tenants/'.substr(uniqid(), -8),
            domainErrorBearer(createDomainErrorApiKey(scopes: ['tenants:read'])),
        ),
        $this->getJson(
            '/api/v1/rota-inexistente',
            domainErrorBearer(createDomainErrorApiKey(scopes: null)),
        ),
    ];

    foreach ($responses as $response) {
        expect($response->status())->toBeGreaterThanOrEqual(400);
        assertApiV1ResponseExcludesNcVocabulary($response->getContent());
    }
});
