<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AppCatalogEntry;
use App\Models\ClusterServer;
use App\Models\Operator;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function createAppCatalogApiKey(?array $scopes = null): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'App catalog API test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
    ]);

    return $rawToken;
}

function appCatalogApiBearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function validAppCatalogCreatePayload(string $appId = 'mail'): array
{
    return [
        'app_id' => $appId,
        'label' => 'Mail',
        'description' => 'Email integration',
        'category' => 'collaboration',
        'is_active' => true,
    ];
}

function seedAppCatalogEntry(string $appId, ?string $clusterId = null): AppCatalogEntry
{
    return AppCatalogEntry::create([
        'id' => Str::uuid()->toString(),
        'app_id' => $appId,
        'label' => ucfirst($appId),
        'is_active' => true,
        'cluster_server_id' => $clusterId,
    ]);
}

it('GET /api/v1/app-catalog returns catalog list with product read scope', function (): void {
    seedAppCatalogEntry('dashboard');

    $rawToken = createAppCatalogApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/app-catalog', appCatalogApiBearer($rawToken));

    $response->assertOk();
    $response->assertJsonPath('data.0.app_id', 'dashboard');
    $response->assertJsonPath('data.0.label', 'Dashboard');
});

it('GET /api/v1/app-catalog filters by cluster_server_id query param', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    seedAppCatalogEntry('mail');
    seedAppCatalogEntry('deck', $cluster->id);

    $rawToken = createAppCatalogApiKey(scopes: ['product:read']);

    $response = $this->getJson(
        '/api/v1/app-catalog?cluster_server_id='.$cluster->id,
        appCatalogApiBearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['app_id' => 'mail']);
    $response->assertJsonFragment(['app_id' => 'deck']);
});

it('GET /api/v1/app-catalog/{app_id} returns single catalog entry', function (): void {
    seedAppCatalogEntry('spreed');

    $rawToken = createAppCatalogApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/app-catalog/spreed', appCatalogApiBearer($rawToken));

    $response->assertOk();
    $response->assertJsonPath('data.app_id', 'spreed');
    $response->assertJsonPath('data.is_active', true);
});

it('denies GET /api/v1/app-catalog without product read scope', function (): void {
    $rawToken = createAppCatalogApiKey(scopes: ['tenants:read']);

    $response = $this->getJson('/api/v1/app-catalog', appCatalogApiBearer($rawToken));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'forbidden_scope');
});

it('POST /api/v1/app-catalog creates entry with product write scope', function (): void {
    $rawToken = createAppCatalogApiKey(scopes: ['product:write']);
    $appId = 'deck-'.substr(uniqid(), -6);

    $response = $this->postJson(
        '/api/v1/app-catalog',
        validAppCatalogCreatePayload($appId),
        appCatalogApiBearer($rawToken),
    );

    $response->assertCreated();
    $response->assertJsonPath('data.app_id', $appId);
    $this->assertDatabaseHas('app_catalog_entries', [
        'app_id' => $appId,
        'label' => 'Mail',
        'is_active' => true,
    ]);
});

it('denies POST /api/v1/app-catalog without product write scope', function (): void {
    $rawToken = createAppCatalogApiKey(scopes: ['product:read']);

    $response = $this->postJson(
        '/api/v1/app-catalog',
        validAppCatalogCreatePayload('blocked-'.substr(uniqid(), -6)),
        appCatalogApiBearer($rawToken),
    );

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'forbidden_scope');
});

it('PATCH /api/v1/app-catalog/{app_id} updates entry with product write scope', function (): void {
    seedAppCatalogEntry('mail');

    $rawToken = createAppCatalogApiKey(scopes: ['product:write']);

    $response = $this->patchJson(
        '/api/v1/app-catalog/mail',
        ['label' => 'Mail Updated', 'is_active' => false],
        appCatalogApiBearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonPath('data.label', 'Mail Updated');
    $this->assertDatabaseHas('app_catalog_entries', [
        'app_id' => 'mail',
        'label' => 'Mail Updated',
        'is_active' => false,
    ]);
});

it('GET /api/v1/app-catalog/{app_id} returns 404 for unknown app_id', function (): void {
    seedAppCatalogEntry('mail');

    $rawToken = createAppCatalogApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/app-catalog/missing-app', appCatalogApiBearer($rawToken));

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'app_catalog_not_found');
});
