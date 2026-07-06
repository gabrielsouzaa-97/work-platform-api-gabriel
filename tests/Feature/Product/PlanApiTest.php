<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AppCatalogEntry;
use App\Models\Operator;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function createPlanApiKey(?array $scopes = null): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Plan API test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
    ]);

    return $rawToken;
}

function planApiBearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function validPlanCreatePayload(string $slug = 'starter'): array
{
    return [
        'slug' => $slug,
        'name' => 'Starter Plan',
        'description' => 'Entry tier',
        'default_quota' => '5 GB',
        'max_users' => 50,
        'status' => 'active',
        'app_ids' => [],
    ];
}

it('registers product read and write scopes in api-scopes config', function (): void {
    $scopes = config('api-scopes.scopes', []);

    expect($scopes)->toHaveKey('product:read')
        ->and($scopes)->toHaveKey('product:write');
});

it('GET /api/v1/plans returns plan list with product read scope', function (): void {
    Plan::create([
        'slug' => 'default',
        'name' => 'Default',
        'default_quota' => '5 GB',
        'is_default' => true,
        'status' => 'active',
    ]);

    $rawToken = createPlanApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/plans', planApiBearer($rawToken));

    $response->assertOk();
    $response->assertJsonPath('data.0.slug', 'default');
    $response->assertJsonPath('data.0.default_quota', '5 GB');
});

it('GET /api/v1/plans/{slug} returns single plan resource', function (): void {
    Plan::create([
        'slug' => 'pro',
        'name' => 'Pro',
        'default_quota' => '20 GB',
        'is_default' => false,
        'status' => 'active',
    ]);

    $rawToken = createPlanApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/plans/pro', planApiBearer($rawToken));

    $response->assertOk();
    $response->assertJsonPath('data.slug', 'pro');
    $response->assertJsonPath('data.name', 'Pro');
    $response->assertJsonMissingPath('data.max_apps');
});

it('GET /api/v1/plans/{slug} returns plan_not_found for missing slug', function (): void {
    $rawToken = createPlanApiKey(scopes: ['product:read']);

    $response = $this->getJson('/api/v1/plans/missing-plan', planApiBearer($rawToken));

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'plan_not_found');
});

it('PATCH /api/v1/plans/{slug} returns plan_not_found for missing slug', function (): void {
    $rawToken = createPlanApiKey(scopes: ['product:write']);

    $response = $this->patchJson(
        '/api/v1/plans/missing-plan',
        ['name' => 'Nope'],
        planApiBearer($rawToken),
    );

    $response->assertNotFound();
    $response->assertJsonPath('error.code', 'plan_not_found');
});

it('denies GET /api/v1/plans without product read scope', function (): void {
    $rawToken = createPlanApiKey(scopes: ['tenants:read']);

    $response = $this->getJson('/api/v1/plans', planApiBearer($rawToken));

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'forbidden_scope');
});

it('POST /api/v1/plans ignores max_apps in request body', function (): void {
    $rawToken = createPlanApiKey(scopes: ['product:write']);
    $slug = 'no-max-apps-'.substr(uniqid(), -6);

    $response = $this->postJson(
        '/api/v1/plans',
        array_merge(validPlanCreatePayload($slug), ['max_apps' => 99]),
        planApiBearer($rawToken),
    );

    $response->assertCreated();
    $response->assertJsonMissingPath('data.max_apps');
    $plan = Plan::findOrFail($slug);
    expect($plan->getAttributes())->not->toHaveKey('max_apps');
});

it('POST /api/v1/plans creates plan with product write scope', function (): void {
    $rawToken = createPlanApiKey(scopes: ['product:write']);
    $slug = 'biz-'.substr(uniqid(), -6);

    $response = $this->postJson(
        '/api/v1/plans',
        validPlanCreatePayload($slug),
        planApiBearer($rawToken),
    );

    $response->assertCreated();
    $response->assertJsonPath('data.slug', $slug);
    $this->assertDatabaseHas('plans', [
        'slug' => $slug,
        'default_quota' => '5 GB',
        'status' => 'active',
    ]);
});

it('denies POST /api/v1/plans without product write scope', function (): void {
    $rawToken = createPlanApiKey(scopes: ['product:read']);

    $response = $this->postJson(
        '/api/v1/plans',
        validPlanCreatePayload('blocked-'.substr(uniqid(), -6)),
        planApiBearer($rawToken),
    );

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'forbidden_scope');
});

it('PATCH /api/v1/plans/{slug} updates plan fields with product write scope', function (): void {
    Plan::create([
        'slug' => 'patch-me',
        'name' => 'Before',
        'default_quota' => '5 GB',
        'is_default' => false,
        'status' => 'active',
    ]);

    $rawToken = createPlanApiKey(scopes: ['product:write']);

    $response = $this->patchJson(
        '/api/v1/plans/patch-me',
        ['name' => 'After', 'default_quota' => '10 GB'],
        planApiBearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonPath('data.name', 'After');
    $this->assertDatabaseHas('plans', [
        'slug' => 'patch-me',
        'name' => 'After',
        'default_quota' => '10 GB',
    ]);
});

it('POST /api/v1/plans with is_default clears previous default plan', function (): void {
    Plan::create([
        'slug' => 'legacy-default',
        'name' => 'Legacy',
        'default_quota' => '5 GB',
        'is_default' => true,
        'status' => 'active',
    ]);

    $rawToken = createPlanApiKey(scopes: ['product:write']);
    $slug = 'new-default-'.substr(uniqid(), -6);

    $this->postJson(
        '/api/v1/plans',
        array_merge(validPlanCreatePayload($slug), ['is_default' => true]),
        planApiBearer($rawToken),
    )->assertCreated();

    expect(Plan::where('is_default', true)->count())->toBe(1);
    expect(Plan::find('legacy-default')?->is_default)->toBeFalse();
    expect(Plan::find($slug)?->is_default)->toBeTrue();
});

it('POST /api/v1/plans persists app_ids into plan_apps junction', function (): void {
    $mailId = AppCatalogEntry::create([
        'app_id' => 'mail',
        'label' => 'Mail',
        'is_active' => true,
    ])->id;
    AppCatalogEntry::create([
        'app_id' => 'deck',
        'label' => 'Deck',
        'is_active' => true,
    ]);

    $rawToken = createPlanApiKey(scopes: ['product:write']);
    $slug = 'apps-plan-'.substr(uniqid(), -6);

    $response = $this->postJson(
        '/api/v1/plans',
        array_merge(validPlanCreatePayload($slug), ['app_ids' => ['mail', 'deck']]),
        planApiBearer($rawToken),
    );

    $response->assertCreated();
    expect($response->json('data.app_ids'))->toEqual(['deck', 'mail']);
    $this->assertDatabaseHas('plan_apps', [
        'plan_slug' => $slug,
        'app_catalog_id' => $mailId,
    ]);
});

it('PATCH /api/v1/plans/{slug} replaces app_ids in plan_apps', function (): void {
    $mailId = AppCatalogEntry::create([
        'app_id' => 'mail',
        'label' => 'Mail',
        'is_active' => true,
    ])->id;
    $calendarId = AppCatalogEntry::create([
        'app_id' => 'calendar',
        'label' => 'Calendar',
        'is_active' => true,
    ])->id;

    Plan::create([
        'slug' => 'swap-apps',
        'name' => 'Swap',
        'default_quota' => '5 GB',
        'is_default' => false,
        'status' => 'active',
    ]);
    DB::table('plan_apps')->insert([
        'plan_slug' => 'swap-apps',
        'app_catalog_id' => $mailId,
    ]);

    $rawToken = createPlanApiKey(scopes: ['product:write']);

    $this->patchJson(
        '/api/v1/plans/swap-apps',
        ['app_ids' => ['calendar']],
        planApiBearer($rawToken),
    )->assertOk()->assertJsonPath('data.app_ids', ['calendar']);

    $this->assertDatabaseMissing('plan_apps', [
        'plan_slug' => 'swap-apps',
        'app_catalog_id' => $mailId,
    ]);
    $this->assertDatabaseHas('plan_apps', [
        'plan_slug' => 'swap-apps',
        'app_catalog_id' => $calendarId,
    ]);
});
