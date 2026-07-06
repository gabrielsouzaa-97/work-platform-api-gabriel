<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AppCatalogEntry;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Models\Plan;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    config([
        'platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog.json'),
        'platform.suite_catalog.default_mode' => true,
    ]);
});

function planInheritanceCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function planInheritanceOperator(): Operator
{
    return Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
}

function seedCatalogApp(string $appId): string
{
    return AppCatalogEntry::create([
        'app_id' => $appId,
        'label' => ucfirst($appId),
        'is_active' => true,
    ])->id;
}

function seedPlanWithCatalogApps(string $slug, array $appIds): Plan
{
    Plan::create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'default_quota' => '5 GB',
        'is_default' => false,
        'status' => 'active',
    ]);

    foreach ($appIds as $appId) {
        DB::table('plan_apps')->insert([
            'plan_slug' => $slug,
            'app_catalog_id' => seedCatalogApp($appId),
        ]);
    }

    return Plan::findOrFail($slug);
}

function mockPlanInheritanceSsh(string $jobId, ?callable $assertArgs = null): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($cluster, $cmd, $args, $stdin = null) use ($assertArgs): bool {
            if ($assertArgs !== null && ! $assertArgs($args, $stdin)) {
                return false;
            }

            return true;
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);
}

function assertAppsArgv(array $args, array $expectedAppIds): bool
{
    $appsArg = collect($args)->first(fn ($arg) => is_string($arg) && str_starts_with($arg, '--apps='));

    if (! is_string($appsArg)) {
        return false;
    }

    $actual = explode(',', substr($appsArg, strlen('--apps=')));
    sort($actual);
    $expected = $expectedAppIds;
    sort($expected);

    return $actual === $expected;
}

function createPlanInheritanceApiKey(): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Plan inheritance API key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:write'],
    ]);

    return $rawToken;
}

it('POST /api/customers with plan_slug and omitted apps inherits plan app_ids in SSH argv', function (): void {
    $cluster = planInheritanceCluster();
    $operator = planInheritanceOperator();
    seedPlanWithCatalogApps('starter', ['mail', 'dashboard']);
    $jobId = Str::uuid()->toString();
    $slug = 'inherit-co-'.substr(uniqid(), -6);

    mockPlanInheritanceSsh($jobId, fn (array $args): bool => assertAppsArgv($args, ['mail', 'dashboard']));

    $this->actingAs($operator)
        ->postJson('/api/customers', [
            'slug' => $slug,
            'cluster_server_id' => $cluster->id,
            'domain' => "{$slug}.example.com",
            'plan_slug' => 'starter',
        ])
        ->assertStatus(201);
});

it('POST /api/v1/tenants with plan_slug and omitted apps inherits plan app_ids in SSH argv', function (): void {
    $cluster = planInheritanceCluster();
    seedPlanWithCatalogApps('starter', ['mail', 'dashboard', 'spreed']);
    $jobId = Str::uuid()->toString();
    $slug = 'inherit-v1-'.substr(uniqid(), -6);
    $rawToken = createPlanInheritanceApiKey();

    mockPlanInheritanceSsh($jobId, fn (array $args): bool => assertAppsArgv($args, ['mail', 'dashboard', 'spreed']));

    $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'cluster_server_id' => $cluster->id,
            'domain' => "{$slug}.example.com",
            'plan_slug' => 'starter',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(202);
});

it('POST /api/customers accepts explicit apps subset of plan when plan_slug is present', function (): void {
    $cluster = planInheritanceCluster();
    $operator = planInheritanceOperator();
    seedPlanWithCatalogApps('starter', ['mail', 'dashboard', 'spreed']);
    $jobId = Str::uuid()->toString();
    $slug = 'subset-co-'.substr(uniqid(), -6);

    mockPlanInheritanceSsh($jobId, fn (array $args): bool => assertAppsArgv($args, ['mail']));

    $this->actingAs($operator)
        ->postJson('/api/customers', [
            'slug' => $slug,
            'cluster_server_id' => $cluster->id,
            'domain' => "{$slug}.example.com",
            'plan_slug' => 'starter',
            'apps' => ['mail'],
        ])
        ->assertStatus(201);
});

it('POST /api/customers rejects app outside plan with 422 when plan_slug is present', function (): void {
    $cluster = planInheritanceCluster();
    $operator = planInheritanceOperator();
    seedPlanWithCatalogApps('starter', ['mail', 'dashboard']);
    seedCatalogApp('deck');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson('/api/customers', [
            'slug' => 'outside-plan-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'domain' => 'outside-plan.example.com',
            'plan_slug' => 'starter',
            'apps' => ['deck'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['apps']);
});

it('POST /api/v1/tenants rejects app outside plan with 422 when plan_slug is present', function (): void {
    $cluster = planInheritanceCluster();
    seedPlanWithCatalogApps('starter', ['mail', 'dashboard']);
    seedCatalogApp('deck');
    $rawToken = createPlanInheritanceApiKey();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => 'outside-v1-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'domain' => 'outside-v1.example.com',
            'plan_slug' => 'starter',
            'apps' => ['deck'],
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['apps']);
});

it('provision still validates apps against active suite catalog upstream', function (): void {
    $cluster = planInheritanceCluster();
    $operator = planInheritanceOperator();
    seedPlanWithCatalogApps('starter', ['mail', 'richdocuments']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson('/api/customers', [
            'slug' => 'planned-upstream-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'domain' => 'planned-upstream.example.com',
            'plan_slug' => 'starter',
            'apps' => ['richdocuments'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['apps']);
});
