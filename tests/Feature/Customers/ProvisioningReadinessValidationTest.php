<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\Plan;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    config([
        'platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog.json'),
        'platform.suite_catalog.default_mode' => true,
        'platform.image_mode.default_mode' => false,
    ]);
});

function readinessValidationOperator(): Operator
{
    return Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
}

function readinessValidationCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function readinessValidationV1Token(): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Readiness validation v1 key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:write'],
        'allowed_tenant_slugs' => null,
    ]);

    return $rawToken;
}

function readinessValidationV1Headers(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function mockReadinessValidationProvisionSsh(string $jobId): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);
}

function assertLegacyReadinessViolation(TestResponse $response): void
{
    $response->assertStatus(422);

    $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);

    expect($encoded)
        ->toContain('LEGACY_READINESS_UNSATISFIABLE')
        ->and($encoded)->toContain('missing_preconditions')
        ->and($encoded)->toMatch('/image_mode/i');
}

function seedEmptyPlan(string $slug = 'empty-plan'): Plan
{
    Plan::create([
        'slug' => $slug,
        'name' => 'Empty Plan',
        'default_quota' => '5 GB',
        'is_default' => false,
        'status' => 'active',
    ]);

    return Plan::findOrFail($slug);
}

it('POST /api/customers with legacy default payload returns 422 LEGACY_READINESS_UNSATISFIABLE', function (): void {
    $cluster = readinessValidationCluster();
    $operator = readinessValidationOperator();
    $slug = 'legacy-def-'.substr(uniqid(), -6);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
    ]);

    assertLegacyReadinessViolation($response);
    expect(Customer::find($slug))->toBeNull()
        ->and(Job::count())->toBe(0);
});

it('POST /api/customers with apps mail only on legacy path returns 422', function (): void {
    $cluster = readinessValidationCluster();
    $operator = readinessValidationOperator();
    $slug = 'legacy-mail-'.substr(uniqid(), -6);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'apps' => ['mail'],
    ]);

    assertLegacyReadinessViolation($response);
    expect(Customer::find($slug))->toBeNull();
});

it('POST /api/v1/tenants with image_mode true does not 422 for readiness', function (): void {
    $cluster = readinessValidationCluster();
    $slug = 'ready-img-'.substr(uniqid(), -6);
    $jobId = Str::uuid()->toString();

    mockReadinessValidationProvisionSsh($jobId);
    $rawToken = readinessValidationV1Token();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => "{$slug}.example.com",
            'cluster_server_id' => $cluster->id,
            'image_mode' => true,
        ],
        readinessValidationV1Headers($rawToken),
    );

    $response->assertStatus(202);
    expect(Customer::where('slug', $slug)->exists())->toBeTrue();
});

it('POST /api/customers with empty plan apps on legacy path returns 422', function (): void {
    $cluster = readinessValidationCluster();
    $operator = readinessValidationOperator();
    $slug = 'legacy-plan-'.substr(uniqid(), -6);
    $plan = seedEmptyPlan('legacy-empty');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'plan_slug' => $plan->slug,
    ]);

    assertLegacyReadinessViolation($response);
    expect(Customer::find($slug))->toBeNull();
});

it('POST /api/v1/tenants with legacy default payload returns 422 before provision', function (): void {
    $cluster = readinessValidationCluster();
    $slug = 'v1-legacy-'.substr(uniqid(), -6);
    $rawToken = readinessValidationV1Token();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => "{$slug}.example.com",
            'cluster_server_id' => $cluster->id,
        ],
        readinessValidationV1Headers($rawToken),
    );

    assertLegacyReadinessViolation($response);
    expect(Customer::where('slug', $slug)->exists())->toBeFalse();
});

it('POST /api/v1/tenants with apps mail only on legacy path returns 422', function (): void {
    $cluster = readinessValidationCluster();
    $slug = 'v1-mail-'.substr(uniqid(), -6);
    $rawToken = readinessValidationV1Token();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'client' => $slug,
            'fqdn' => "{$slug}.example.com",
            'cluster_server_id' => $cluster->id,
            'shell' => true,
            'apps' => ['mail'],
        ],
        readinessValidationV1Headers($rawToken),
    );

    assertLegacyReadinessViolation($response);
    expect(Customer::where('slug', $slug)->exists())->toBeFalse();
});
