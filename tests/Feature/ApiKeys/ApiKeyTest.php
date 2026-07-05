<?php

declare(strict_types=1);

use App\Http\Livewire\ApiKeys\Index;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function makeApiKeyScopeTestTenant(string $slug): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => ClusterServer::factory()->create(['status' => 'active'])->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function createApiKeyScopeTestToken(?array $scopes = null): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Scope enforcement test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
    ]);

    return $rawToken;
}

function apiKeyScopeBearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

it('admin can access api-keys page', function () {
    $admin = Operator::factory()->admin()->create();

    actingAs($admin)
        ->get(route('api-keys.index'))
        ->assertOk();
});

it('admin can generate a new api key and raw token is returned once', function () {
    $admin = Operator::factory()->admin()->create();

    $component = Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('openCreate')
        ->assertSet('showCreateModal', true)
        ->set('createName', 'Integration ERP')
        ->call('create')
        ->assertSet('showCreateModal', false)
        ->assertSet('showTokenReveal', true);

    $rawToken = $component->get('createdToken');

    expect($rawToken)->toStartWith('sk_')
        ->and(strlen($rawToken))->toBe(67);

    $this->assertDatabaseHas('api_keys', ['name' => 'Integration ERP']);

    $apiKey = ApiKey::where('name', 'Integration ERP')->firstOrFail();

    expect($apiKey->token_hash)->toBe(hash('sha256', $rawToken))
        ->and($apiKey->revoked_at)->toBeNull()
        ->and($apiKey->scopes)->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'api_key.create',
        'resource_type' => 'api_key',
        'resource_id' => $apiKey->id,
    ]);
});

it('raw token is not stored in plain text — only hash exists in database', function () {
    $admin = Operator::factory()->admin()->create();

    $component = Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('createName', 'Security Test Key')
        ->call('create');

    $rawToken = $component->get('createdToken');
    $apiKey = ApiKey::where('name', 'Security Test Key')->firstOrFail();

    expect($apiKey->token_hash)->not->toBe($rawToken)
        ->and($apiKey->token_hash)->toBe(hash('sha256', $rawToken));
});

it('admin can revoke an active api key', function () {
    $admin = Operator::factory()->admin()->create();
    $apiKey = ApiKey::factory()->create(['name' => 'Key to revoke']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('revoke', $apiKey->id);

    expect($apiKey->refresh()->revoked_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'api_key.revoke',
        'resource_type' => 'api_key',
        'resource_id' => $apiKey->id,
    ]);
});

it('non-admin operator cannot generate api keys and gets 403', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);

    actingAs($operador)
        ->get(route('api-keys.index'))
        ->assertForbidden();
});

it('bearer token authenticates against /api/queue and returns 200', function () {
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'CI pipeline',
        'token_hash' => hash('sha256', $rawToken),
    ]);

    $this->getJson('/api/queue', ['Authorization' => "Bearer {$rawToken}"])
        ->assertOk();
});

it('invalid bearer token returns 401', function () {
    $this->getJson('/api/queue', ['Authorization' => 'Bearer sk_invalid_token'])
        ->assertUnauthorized();
});

it('revoked bearer token returns 401', function () {
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Revoked key',
        'token_hash' => hash('sha256', $rawToken),
        'revoked_at' => now(),
    ]);

    $this->getJson('/api/queue', ['Authorization' => "Bearer {$rawToken}"])
        ->assertUnauthorized();
});

it('creates api key with selected v1 scopes and persists exact array in database', function () {
    $admin = Operator::factory()->admin()->create();
    $selectedScopes = ['tenants:read', 'jobs:read'];

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('createName', 'Scoped integration key')
        ->set('createScopes', $selectedScopes)
        ->call('create')
        ->assertSet('showTokenReveal', true);

    $apiKey = ApiKey::where('name', 'Scoped integration key')->firstOrFail();

    expect($apiKey->scopes)->toBe($selectedScopes);

    $audit = AuditLog::query()
        ->where('action', 'api_key.create')
        ->where('resource_id', $apiKey->id)
        ->firstOrFail();

    expect($audit->payload)->toMatchArray([
        'name' => 'Scoped integration key',
        'scopes' => $selectedScopes,
    ]);
});

it('creates api key without scope selection as unrestricted null scopes', function () {
    $admin = Operator::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('createName', 'Unrestricted key')
        ->call('create');

    $apiKey = ApiKey::where('name', 'Unrestricted key')->firstOrFail();

    expect($apiKey->scopes)->toBeNull();
});

it('rejects invalid scope on create and does not persist api key', function () {
    $admin = Operator::factory()->admin()->create();
    $countBefore = ApiKey::count();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('createName', 'Invalid scope key')
        ->set('createScopes', ['occ:write'])
        ->call('create')
        ->assertHasErrors(['createScopes.0']);

    expect(ApiKey::count())->toBe($countBefore);
});

it('collapses explicit empty scopes array to null unrestricted key', function () {
    $admin = Operator::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('createName', 'Empty scopes key')
        ->set('createScopes', [])
        ->call('create');

    $apiKey = ApiKey::where('name', 'Empty scopes key')->firstOrFail();

    expect($apiKey->scopes)->toBeNull();
});

it('lists null-scoped api key with irrestrita badge', function () {
    $admin = Operator::factory()->admin()->create();

    ApiKey::factory()->create([
        'name' => 'Listed unrestricted key',
        'scopes' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Listed unrestricted key')
        ->assertSee('irrestrita');
});

it('lists wildcard-scoped api key with irrestrita badge', function () {
    $admin = Operator::factory()->admin()->create();

    ApiKey::factory()->create([
        'name' => 'Listed wildcard key',
        'scopes' => ['*'],
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Listed wildcard key')
        ->assertSee('irrestrita');
});

it('lists api key scopes as individual badges in listing', function () {
    $admin = Operator::factory()->admin()->create();

    ApiKey::factory()->create([
        'name' => 'Listed scoped key',
        'scopes' => ['tenants:read', 'jobs:read'],
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Listed scoped key')
        ->assertSee('tenants:read')
        ->assertSee('jobs:read')
        ->assertDontSee('irrestrita');
});

it('enforces EnsureApiKeyScope for key created with tenants read only', function () {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    $slug = 'scope-read-'.substr(uniqid(), -6);
    makeApiKeyScopeTestTenant($slug);
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $rawToken = createApiKeyScopeTestToken(scopes: ['tenants:read']);

    $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'new-tenant-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'domain' => 'new.example.com',
        ],
        apiKeyScopeBearer($rawToken),
    )
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden_scope');

    $this->getJson("/api/v1/tenants/{$slug}", apiKeyScopeBearer($rawToken))
        ->assertOk();
});
