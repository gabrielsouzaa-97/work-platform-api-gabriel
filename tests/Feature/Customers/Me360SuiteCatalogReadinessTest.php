<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
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

function me360ReadinessCluster(bool $legacyCapable = true): ClusterServer
{
    $factory = ClusterServer::factory();

    if ($legacyCapable) {
        $factory = $factory->legacyMe360Capable();
    }

    return $factory->create(['status' => 'active']);
}

function me360ReadinessV1Token(): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Me360 suite catalog readiness v1 key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:write'],
        'allowed_tenant_slugs' => null,
    ]);

    return $rawToken;
}

function me360ReadinessV1Headers(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function mockMe360ReadinessProvisionSsh(string $jobId): void
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

function assertMe360LegacyReadinessViolation(TestResponse $response): void
{
    $response->assertStatus(422);

    $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);

    expect($encoded)
        ->toContain('LEGACY_READINESS_UNSATISFIABLE')
        ->and($encoded)->toContain('missing_preconditions')
        ->and($encoded)->toMatch('/image_mode/i');
}

it('POST /api/v1/tenants with legacy_me360_capable cluster and me360 catalog returns 202', function (): void {
    config(['platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog_me360.json')]);

    $cluster = me360ReadinessCluster(legacyCapable: true);
    $slug = 'me360-ready-'.substr(uniqid(), -6);
    $jobId = Str::uuid()->toString();

    mockMe360ReadinessProvisionSsh($jobId);
    $rawToken = me360ReadinessV1Token();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => "{$slug}.example.com",
            'cluster_server_id' => $cluster->id,
        ],
        me360ReadinessV1Headers($rawToken),
    );

    $response->assertStatus(202);
    expect(Customer::where('slug', $slug)->exists())->toBeTrue();
});

it('POST /api/v1/tenants with legacy_me360_capable cluster and default catalog returns 422', function (): void {
    $cluster = me360ReadinessCluster(legacyCapable: true);
    $slug = 'me360-block-'.substr(uniqid(), -6);
    $rawToken = me360ReadinessV1Token();

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
        me360ReadinessV1Headers($rawToken),
    );

    assertMe360LegacyReadinessViolation($response);
    expect(Customer::where('slug', $slug)->exists())->toBeFalse()
        ->and(Job::count())->toBe(0);
});

it('POST /api/v1/tenants with incapable cluster and me360 catalog returns 422', function (): void {
    config(['platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog_me360.json')]);

    $cluster = me360ReadinessCluster(legacyCapable: false);
    $slug = 'me360-incap-'.substr(uniqid(), -6);
    $rawToken = me360ReadinessV1Token();

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
        me360ReadinessV1Headers($rawToken),
    );

    assertMe360LegacyReadinessViolation($response);
    expect(Customer::where('slug', $slug)->exists())->toBeFalse();
});

it('POST /api/customers with legacy_me360_capable cluster and me360 catalog returns 202', function (): void {
    config(['platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog_me360.json')]);

    $cluster = me360ReadinessCluster(legacyCapable: true);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $slug = 'me360-legacy-'.substr(uniqid(), -6);
    $jobId = Str::uuid()->toString();

    mockMe360ReadinessProvisionSsh($jobId);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
    ]);

    $response->assertStatus(201);
    expect(Customer::find($slug))->not->toBeNull();
});
