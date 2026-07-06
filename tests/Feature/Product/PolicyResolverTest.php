<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AppCatalogEntry;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\TenantUser;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function policyResolverCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function policyResolverPlan(string $slug, ?int $maxUsers = null): Plan
{
    return Plan::create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'default_quota' => '5 GB',
        'max_users' => $maxUsers,
        'is_default' => false,
        'status' => 'active',
    ]);
}

function policyResolverSeedCatalogApp(string $appId): string
{
    return AppCatalogEntry::create([
        'app_id' => $appId,
        'label' => ucfirst($appId),
        'is_active' => true,
    ])->id;
}

function policyResolverSeedPlanWithCatalogApps(string $slug, array $appIds): Plan
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
            'app_catalog_id' => policyResolverSeedCatalogApp($appId),
        ]);
    }

    return Plan::findOrFail($slug);
}

function policyResolverCustomer(string $slug, string $clusterId, string $planSlug): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $clusterId,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
        'plan_slug' => $planSlug,
    ]);
}

function policyResolverV1Key(string $tenantSlug, array $scopes): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Policy resolver v1 key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
        'allowed_tenant_slugs' => [$tenantSlug],
    ]);

    return $rawToken;
}

function seedPolicyTenantUser(string $customerSlug, string $username): void
{
    TenantUser::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customerSlug,
        'username' => $username,
        'origin' => 'api',
    ]);
}

it('POST /api/v1/tenants/{slug}/users returns 422 plan_limit_exceeded when max_users reached', function (): void {
    $cluster = policyResolverCluster();
    $slug = 'limit-users-'.substr(uniqid(), -6);
    policyResolverPlan('limited', maxUsers: 2);
    policyResolverCustomer($slug, $cluster->id, 'limited');
    seedPolicyTenantUser($slug, 'alice');
    seedPolicyTenantUser($slug, 'bob');
    $rawToken = policyResolverV1Key($slug, ['users:write']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'carol',
            'password' => 'Secret123!',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    );

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'plan_limit_exceeded');
});

it('max_users plan_limit_exceeded records AuditLog policy_denied', function (): void {
    $cluster = policyResolverCluster();
    $slug = 'audit-users-'.substr(uniqid(), -6);
    policyResolverPlan('audit-plan', maxUsers: 1);
    policyResolverCustomer($slug, $cluster->id, 'audit-plan');
    seedPolicyTenantUser($slug, 'alice');
    $rawToken = policyResolverV1Key($slug, ['users:write']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'bob',
            'password' => 'Secret123!',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(422);

    expect(AuditLog::where('action', 'policy_denied')
        ->where('resource_id', $slug)
        ->exists())->toBeTrue();
});

it('POST /api/v1/tenants/{slug}/apps rejects app outside plan_apps with 422', function (): void {
    $cluster = policyResolverCluster();
    $slug = 'enable-apps-v1-'.substr(uniqid(), -6);
    policyResolverSeedPlanWithCatalogApps('apps-plan', ['calendar']);
    policyResolverSeedCatalogApp('deck');
    policyResolverCustomer($slug, $cluster->id, 'apps-plan');
    $rawToken = policyResolverV1Key($slug, ['apps:write']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->postJson(
        "/api/v1/tenants/{$slug}/apps",
        ['apps' => ['deck']],
        ['Authorization' => "Bearer {$rawToken}"],
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['apps']);
});

it('POST /api/customers/{slug}/apps/enable rejects app outside plan_apps with 422', function (): void {
    $cluster = policyResolverCluster();
    $slug = 'legacy-enable-plan-apps-'.substr(uniqid(), -6);
    policyResolverSeedPlanWithCatalogApps('legacy-apps-plan', ['calendar']);
    policyResolverSeedCatalogApp('deck');
    $customer = policyResolverCustomer($slug, $cluster->id, 'legacy-apps-plan');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", [
            'apps' => ['deck'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['apps']);
});

it('POST /api/customers/{slug}/users enforces max_users via PolicyResolver on legacy route', function (): void {
    $cluster = policyResolverCluster();
    $slug = 'legacy-limit-'.substr(uniqid(), -6);
    policyResolverPlan('legacy-plan', maxUsers: 1);
    $customer = policyResolverCustomer($slug, $cluster->id, 'legacy-plan');
    seedPolicyTenantUser($slug, 'alice');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'bob',
            'password' => 'Secret123!',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'plan_limit_exceeded');
});

it('plan_limit_exceeded AuditLog payload includes limit dimension', function (): void {
    $cluster = policyResolverCluster();
    $slug = 'audit-payload-'.substr(uniqid(), -6);
    policyResolverPlan('payload-plan', maxUsers: 1);
    policyResolverCustomer($slug, $cluster->id, 'payload-plan');
    seedPolicyTenantUser($slug, 'alice');
    $rawToken = policyResolverV1Key($slug, ['users:write']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'bob',
            'password' => 'Secret123!',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(422);

    $log = AuditLog::where('action', 'policy_denied')
        ->where('resource_id', $slug)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->payload['limit'] ?? null)->toBe('max_users');
});

it('PolicyResolver skips SSH when max_users already reached on legacy customers route', function (): void {
    $cluster = policyResolverCluster();
    $slug = 'legacy-no-ssh-'.substr(uniqid(), -6);
    policyResolverPlan('no-ssh-plan', maxUsers: 1);
    $customer = policyResolverCustomer($slug, $cluster->id, 'no-ssh-plan');
    seedPolicyTenantUser($slug, 'alice');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'bob',
            'password' => 'Secret123!',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'plan_limit_exceeded');
});

it('max_users counts inflight users:create jobs before allowing another create', function (): void {
    $cluster = policyResolverCluster();
    $slug = 'inflight-limit-'.substr(uniqid(), -6);
    policyResolverPlan('inflight-plan', maxUsers: 1);
    policyResolverCustomer($slug, $cluster->id, 'inflight-plan');
    $rawToken = policyResolverV1Key($slug, ['users:write']);

    Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'users:create',
        'job_type' => 'users:create',
        'state' => 'queued',
        'idempotency_key' => Str::uuid()->toString(),
        'correlation_id' => Str::uuid()->toString(),
        'queued_at' => now(),
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'bob',
            'password' => 'Secret123!',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(422)
        ->assertJsonPath('error.code', 'plan_limit_exceeded');
});
