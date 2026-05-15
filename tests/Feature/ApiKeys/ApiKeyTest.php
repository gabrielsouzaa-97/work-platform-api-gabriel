<?php

declare(strict_types=1);

use App\Http\Livewire\ApiKeys\Index;
use App\Models\ApiKey;
use App\Models\Operator;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

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
        ->and($apiKey->revoked_at)->toBeNull();

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
