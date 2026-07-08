<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\TenantGroup;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function resolverPermissionsV1(array $overrides = []): array
{
    return array_replace_recursive([
        'schema_version' => 1,
        'users' => ['hire' => true, 'block' => false, 'activate' => false],
        'apps' => ['install_from_store' => false, 'create_integration' => false],
        'audit' => ['read' => false],
    ], $overrides);
}

function seedResolverTemplate(string $slug, array $overrides = []): void
{
    $row = array_merge([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'description' => null,
        'default_quota' => '15 GB',
        'groups' => json_encode(['supervisors', 'staff']),
        'permissions' => json_encode(resolverPermissionsV1()),
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

function resolverCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function resolverCustomer(string $slug, string $clusterId): Customer
{
    Plan::create([
        'slug' => 'default',
        'name' => 'Default',
        'default_quota' => '5 GB',
        'is_default' => true,
        'status' => 'active',
    ]);

    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $clusterId,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
        'plan_slug' => 'default',
    ]);
}

/**
 * @param  list<string>  $names
 */
function seedResolverTenantGroups(string $customerSlug, array $names): void
{
    foreach ($names as $name) {
        TenantGroup::create([
            'id' => Str::uuid()->toString(),
            'customer_slug' => $customerSlug,
            'name' => $name,
            'origin' => 'api',
        ]);
    }
}

function resolverV1ApiKey(string $tenantSlug): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'User template resolver v1 key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['users:write'],
        'allowed_tenant_slugs' => [$tenantSlug],
    ]);

    return $rawToken;
}

function mockResolverSsh(string $jobId, ?callable $assertStdin = null): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($cluster, $cmd, $args, $stdin) use ($assertStdin): bool {
            if ($assertStdin === null) {
                return true;
            }

            return $assertStdin($stdin);
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);
}

it('POST /api/v1/tenants/{slug}/users merges template groups into upstream stdin', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-groups-'.substr(uniqid(), -6);
    resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('supervisor');
    $jobId = Str::uuid()->toString();
    $rawToken = resolverV1ApiKey($slug);

    mockResolverSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['groups'] ?? null) === ['supervisors', 'staff'];
    });

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'alice',
            'password' => 'Secret123!',
            'email' => 'alice@example.com',
            'user_template_slug' => 'supervisor',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    );

    $response->assertStatus(202);
    $response->assertJsonPath('meta.job_id', $jobId);
});

it('POST /api/v1/tenants/{slug}/users merges template default_quota into upstream stdin', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-quota-'.substr(uniqid(), -6);
    resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('collaborator', ['default_quota' => '15 GB']);
    $jobId = Str::uuid()->toString();
    $rawToken = resolverV1ApiKey($slug);

    mockResolverSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['quota'] ?? null) === '15 GB';
    });

    $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'bob',
            'password' => 'Secret123!',
            'user_template_slug' => 'collaborator',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(202);
});

it('POST /api/v1/tenants/{slug}/users explicit groups override template groups', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-override-g-'.substr(uniqid(), -6);
    resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('supervisor');
    seedResolverTenantGroups($slug, ['financeiro']);
    $jobId = Str::uuid()->toString();
    $rawToken = resolverV1ApiKey($slug);

    mockResolverSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['groups'] ?? null) === ['financeiro'];
    });

    $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'carol',
            'password' => 'Secret123!',
            'user_template_slug' => 'supervisor',
            'groups' => ['financeiro'],
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(202);
});

it('POST /api/v1/tenants/{slug}/users explicit empty groups clears template groups', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-clear-g-'.substr(uniqid(), -6);
    resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('supervisor');
    $jobId = Str::uuid()->toString();
    $rawToken = resolverV1ApiKey($slug);

    mockResolverSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && array_key_exists('groups', $decoded)
            && $decoded['groups'] === [];
    });

    $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'cleared',
            'password' => 'Secret123!',
            'user_template_slug' => 'supervisor',
            'groups' => [],
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(202);
});

it('POST /api/v1/tenants/{slug}/users explicit quota overrides template default_quota', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-override-q-'.substr(uniqid(), -6);
    resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('supervisor', ['default_quota' => '15 GB']);
    $jobId = Str::uuid()->toString();
    $rawToken = resolverV1ApiKey($slug);

    mockResolverSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['quota'] ?? null) === '2 GB';
    });

    $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'dave',
            'password' => 'Secret123!',
            'user_template_slug' => 'supervisor',
            'quota' => '2 GB',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(202);
});

it('POST /api/v1/tenants/{slug}/users stores user_template_slug in payload_sanitized', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-payload-'.substr(uniqid(), -6);
    resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('supervisor');
    $jobId = Str::uuid()->toString();
    $rawToken = resolverV1ApiKey($slug);

    mockResolverSsh($jobId);

    $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'eve',
            'password' => 'Secret123!',
            'user_template_slug' => 'supervisor',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    )->assertStatus(202);

    $job = Job::find($jobId);
    expect($job)->not->toBeNull()
        ->and($job->payload_sanitized['user_template_slug'] ?? null)->toBe('supervisor');
});

it('POST /api/v1/tenants/{slug}/users rejects unknown user_template_slug with 422', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-missing-'.substr(uniqid(), -6);
    resolverCustomer($slug, $cluster->id);
    $rawToken = resolverV1ApiKey($slug);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'frank',
            'password' => 'Secret123!',
            'user_template_slug' => 'missing-template',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['user_template_slug']);
});

it('POST /api/v1/tenants/{slug}/users rejects inactive user_template_slug with 422', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-inactive-'.substr(uniqid(), -6);
    resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('retired', ['status' => 'inactive']);
    $rawToken = resolverV1ApiKey($slug);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        "/api/v1/tenants/{$slug}/users",
        [
            'username' => 'grace',
            'password' => 'Secret123!',
            'user_template_slug' => 'retired',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['user_template_slug']);
});

it('POST /api/customers/{slug}/users merges template via UserCreateTemplateResolver', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-legacy-'.substr(uniqid(), -6);
    $customer = resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('supervisor');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    mockResolverSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['groups'] ?? null) === ['supervisors', 'staff'];
    });

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'henry',
            'password' => 'Secret123!',
            'user_template_slug' => 'supervisor',
        ])
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});

it('POST /api/customers/{slug}/users groups null inherits template groups (CQ-F17-003)', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-null-g-'.substr(uniqid(), -6);
    $customer = resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('supervisor');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    mockResolverSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['groups'] ?? null) === ['supervisors', 'staff'];
    });

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'null-groups',
            'password' => 'Secret123!',
            'user_template_slug' => 'supervisor',
            'groups' => null,
        ])
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});

it('POST /api/customers/{slug}/users groups empty array clears template groups (CQ-F17-003)', function (): void {
    $cluster = resolverCluster();
    $slug = 'tpl-empty-g-'.substr(uniqid(), -6);
    $customer = resolverCustomer($slug, $cluster->id);
    seedResolverTemplate('supervisor');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    mockResolverSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && array_key_exists('groups', $decoded)
            && $decoded['groups'] === [];
    });

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'empty-groups',
            'password' => 'Secret123!',
            'user_template_slug' => 'supervisor',
            'groups' => [],
        ])
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});
