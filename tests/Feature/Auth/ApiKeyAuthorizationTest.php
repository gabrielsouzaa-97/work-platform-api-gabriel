<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
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
