<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AppCatalogEntry;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function userTemplateApiKey(?array $scopes = null): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'User template API test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
    ]);

    return $rawToken;
}

function userTemplateBearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function permissionsSchemaV1(array $overrides = []): array
{
    return array_replace_recursive([
        'schema_version' => 1,
        'users' => ['hire' => true, 'block' => false, 'activate' => false],
        'apps' => ['install_from_store' => false, 'create_integration' => false],
        'audit' => ['read' => false],
    ], $overrides);
}

function seedUserTemplateRow(string $slug, array $overrides = []): void
{
    $row = array_merge([
        'slug' => $slug,
        'name' => ucfirst(str_replace('-', ' ', $slug)),
        'description' => null,
        'default_quota' => '10 GB',
        'groups' => json_encode(['staff']),
        'permissions' => json_encode(permissionsSchemaV1()),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);

    if (isset($row['groups']) && is_array($row['groups'])) {
        $row['groups'] = json_encode($row['groups']);
    }

    if (isset($row['permissions']) && is_array($row['permissions'])) {
        $row['permissions'] = json_encode($row['permissions']);
    }

    DB::table('user_templates')->insert($row);
}

function validUserTemplateCreatePayload(string $slug = 'supervisor'): array
{
    return [
        'slug' => $slug,
        'name' => 'Supervisor',
        'description' => 'Supervisor profile',
        'default_quota' => '10 GB',
        'groups' => ['supervisors', 'staff'],
        'permissions' => permissionsSchemaV1(),
        'app_ids' => [],
    ];
}

it('GET /api/v1/user-templates returns template list with product read scope', function (): void {
    seedUserTemplateRow('supervisor');

    $rawToken = userTemplateApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/user-templates', userTemplateBearer($rawToken));

    $response->assertOk();
    $response->assertJsonPath('data.0.slug', 'supervisor');
    $response->assertJsonPath('data.0.groups', ['staff']);
    $response->assertJsonPath('data.0.permissions.schema_version', 1);
});

it('GET /api/v1/user-templates/{slug} returns single template resource', function (): void {
    seedUserTemplateRow('collaborator', [
        'name' => 'Collaborator',
        'groups' => ['users'],
    ]);

    $rawToken = userTemplateApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/user-templates/collaborator', userTemplateBearer($rawToken));

    $response->assertOk();
    $response->assertJsonPath('data.slug', 'collaborator');
    $response->assertJsonPath('data.name', 'Collaborator');
    $response->assertJsonPath('data.status', 'active');
});

it('denies GET /api/v1/user-templates without product read scope', function (): void {
    $rawToken = userTemplateApiKey(scopes: ['tenants:read']);

    $response = $this->getJson('/api/v1/user-templates', userTemplateBearer($rawToken));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'forbidden_scope');
});

it('POST /api/v1/user-templates creates template with product write scope', function (): void {
    $rawToken = userTemplateApiKey(scopes: ['product:write']);
    $slug = 'tpl-'.substr(uniqid(), -6);

    $response = $this->postJson(
        '/api/v1/user-templates',
        validUserTemplateCreatePayload($slug),
        userTemplateBearer($rawToken),
    );

    $response->assertCreated();
    $response->assertJsonPath('data.slug', $slug);
    $response->assertJsonPath('data.permissions.users.hire', true);
    $this->assertDatabaseHas('user_templates', [
        'slug' => $slug,
        'status' => 'active',
    ]);
});

it('denies POST /api/v1/user-templates without product write scope', function (): void {
    $rawToken = userTemplateApiKey(scopes: ['product:read']);

    $response = $this->postJson(
        '/api/v1/user-templates',
        validUserTemplateCreatePayload('blocked-'.substr(uniqid(), -6)),
        userTemplateBearer($rawToken),
    );

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'forbidden_scope');
});

it('PATCH /api/v1/user-templates/{slug} updates template with product write scope', function (): void {
    seedUserTemplateRow('patch-me', ['name' => 'Before']);

    $rawToken = userTemplateApiKey(scopes: ['product:write']);

    $response = $this->patchJson(
        '/api/v1/user-templates/patch-me',
        ['name' => 'After', 'default_quota' => '20 GB', 'status' => 'inactive'],
        userTemplateBearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonPath('data.name', 'After');
    $this->assertDatabaseHas('user_templates', [
        'slug' => 'patch-me',
        'name' => 'After',
        'default_quota' => '20 GB',
        'status' => 'inactive',
    ]);
});

it('POST /api/v1/user-templates rejects permissions without schema_version', function (): void {
    $rawToken = userTemplateApiKey(scopes: ['product:write']);
    $payload = validUserTemplateCreatePayload('no-schema-'.substr(uniqid(), -6));
    unset($payload['permissions']['schema_version']);

    $response = $this->postJson(
        '/api/v1/user-templates',
        $payload,
        userTemplateBearer($rawToken),
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['permissions.schema_version']);
});

it('POST /api/v1/user-templates rejects permissions with unknown keys', function (): void {
    $rawToken = userTemplateApiKey(scopes: ['product:write']);
    $payload = validUserTemplateCreatePayload('unknown-key-'.substr(uniqid(), -6));
    $payload['permissions']['billing'] = ['charge' => true];

    $response = $this->postJson(
        '/api/v1/user-templates',
        $payload,
        userTemplateBearer($rawToken),
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['permissions']);
});

it('GET /api/v1/user-templates/{slug} returns 404 for unknown slug', function (): void {
    $rawToken = userTemplateApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/user-templates/missing-template', userTemplateBearer($rawToken));

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'user_template_not_found');
});

it('POST /api/v1/user-templates persists app_ids via user_template_apps junction', function (): void {
    $catalogId = AppCatalogEntry::create([
        'app_id' => 'deck',
        'label' => 'Deck',
        'is_active' => true,
    ])->id;
    $rawToken = userTemplateApiKey(scopes: ['product:write']);
    $slug = 'apps-tpl-'.substr(uniqid(), -6);

    $response = $this->postJson(
        '/api/v1/user-templates',
        array_merge(validUserTemplateCreatePayload($slug), ['app_ids' => ['deck']]),
        userTemplateBearer($rawToken),
    );

    $response->assertCreated();
    $response->assertJsonPath('data.app_ids', ['deck']);
    $this->assertDatabaseHas('user_template_apps', [
        'user_template_slug' => $slug,
        'app_catalog_id' => $catalogId,
    ]);
});
