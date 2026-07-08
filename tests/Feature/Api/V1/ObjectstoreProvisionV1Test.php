<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    config([
        'platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog.json'),
        'platform.suite_catalog.default_mode' => false,
        'platform.image_mode.default_mode' => false,
    ]);
});

function objectstoreV1ApiKey(): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Objectstore v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:write'],
        'allowed_tenant_slugs' => null,
    ]);

    return $rawToken;
}

function objectstoreV1Bearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function mockObjectstoreProvisionSsh(string $jobId, ?callable $assertArgs = null): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (
            ClusterServer $cluster,
            string $cmd,
            array $args,
        ) use ($assertArgs): bool {
            if ($cluster->status !== 'active' || $cmd !== 'nextcloud-manage') {
                return false;
            }

            if ($assertArgs !== null && ! $assertArgs($args)) {
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

function objectstoreFlagIndex(array $args): int|false
{
    return array_search('--objectstore', $args, true);
}

function imageModeFlagIndex(array $args): int|false
{
    return array_search('--image-mode', $args, true);
}

it('POST /api/v1/tenants with objectstore enabled and image_mode dispatches --objectstore and persists flags', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 's3obj-'.substr(uniqid(), -6);

    mockObjectstoreProvisionSsh(
        $jobId,
        function (array $args): bool {
            $imageIdx = imageModeFlagIndex($args);
            $objectstoreIdx = objectstoreFlagIndex($args);

            return $imageIdx !== false
                && $objectstoreIdx !== false
                && $imageIdx < $objectstoreIdx
                && ! in_array('--objectstore-bucket=', $args, true)
                && ! collect($args)->contains(fn (mixed $arg): bool => is_string($arg) && str_starts_with($arg, '--objectstore-bucket='));
        },
    );

    $rawToken = objectstoreV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 's3obj.example.com',
            'cluster_server_id' => $cluster->id,
            'image_mode' => true,
            'objectstore' => ['enabled' => true],
        ],
        objectstoreV1Bearer($rawToken),
    );

    $response->assertStatus(202);
    $response->assertJsonPath('meta.job_id', $jobId);

    $customer = Customer::where('slug', $slug)->first();
    expect($customer)->not->toBeNull()
        ->and($customer->objectstore_enabled)->toBeTrue()
        ->and($customer->objectstore_bucket)->toBeNull();
});

it('POST /api/v1/tenants with objectstore bucket override dispatches --objectstore-bucket', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 's3bkt-'.substr(uniqid(), -6);
    $bucket = 'my-tenant-bucket';

    mockObjectstoreProvisionSsh(
        $jobId,
        fn (array $args): bool => in_array('--objectstore', $args, true)
            && in_array("--objectstore-bucket={$bucket}", $args, true),
    );

    $rawToken = objectstoreV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 's3bkt.example.com',
            'cluster_server_id' => $cluster->id,
            'image_mode' => true,
            'objectstore' => [
                'enabled' => true,
                'bucket' => $bucket,
            ],
        ],
        objectstoreV1Bearer($rawToken),
    );

    $response->assertStatus(202);

    $customer = Customer::where('slug', $slug)->first();
    expect($customer)->not->toBeNull()
        ->and($customer->objectstore_enabled)->toBeTrue()
        ->and($customer->objectstore_bucket)->toBe($bucket);
});

it('POST /api/v1/tenants with objectstore enabled without image_mode returns 422', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $slug = 's3noimg-'.substr(uniqid(), -6);

    $rawToken = objectstoreV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 's3noimg.example.com',
            'cluster_server_id' => $cluster->id,
            'objectstore' => ['enabled' => true],
        ],
        objectstoreV1Bearer($rawToken),
    );

    $response->assertStatus(422);
    expect(Customer::where('slug', $slug)->exists())->toBeFalse();
});

it('POST /api/v1/tenants rejects prohibited objectstore.key with 422', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $slug = 's3sec-'.substr(uniqid(), -6);

    $rawToken = objectstoreV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 's3sec.example.com',
            'cluster_server_id' => $cluster->id,
            'image_mode' => true,
            'objectstore' => [
                'enabled' => true,
                'key' => 'AKIAIOSFODNN7EXAMPLE',
            ],
        ],
        objectstoreV1Bearer($rawToken),
    );

    $response->assertStatus(422);
});

it('POST /api/v1/tenants without objectstore leaves argv unchanged', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 'noobj-'.substr(uniqid(), -6);

    mockObjectstoreProvisionSsh(
        $jobId,
        fn (array $args): bool => ! in_array('--objectstore', $args, true)
            && ! collect($args)->contains(
                fn (mixed $arg): bool => is_string($arg) && str_starts_with($arg, '--objectstore-bucket='),
            ),
    );

    $rawToken = objectstoreV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 'noobj.example.com',
            'cluster_server_id' => $cluster->id,
            'image_mode' => true,
        ],
        objectstoreV1Bearer($rawToken),
    );

    $response->assertStatus(202);

    $customer = Customer::where('slug', $slug)->first();
    expect($customer)->not->toBeNull()
        ->and($customer->objectstore_enabled)->toBeFalse()
        ->and($customer->objectstore_bucket)->toBeNull();
});

it('provision dispatch audit trail does not persist objectstore secrets', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 's3san-'.substr(uniqid(), -6);
    $bucket = 'sanitized-bucket';

    mockObjectstoreProvisionSsh($jobId);

    $rawToken = objectstoreV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 's3san.example.com',
            'cluster_server_id' => $cluster->id,
            'image_mode' => true,
            'objectstore' => [
                'enabled' => true,
                'bucket' => $bucket,
            ],
        ],
        objectstoreV1Bearer($rawToken),
    );

    $response->assertStatus(202);

    $job = Job::find($jobId);
    expect($job)->not->toBeNull();

    $jobPayload = json_encode($job->payload_sanitized ?? []);
    expect($jobPayload)->not->toContain('OBJECTSTORE_S3_KEY')
        ->and($jobPayload)->not->toContain('OBJECTSTORE_S3_SECRET')
        ->and($jobPayload)->not->toContain('AKIA');

    $audit = AuditLog::where('action', 'provision_initiated')
        ->where('resource_id', $slug)
        ->first();
    expect($audit)->not->toBeNull();

    $auditPayload = json_encode($audit->payload ?? []);
    expect($auditPayload)->not->toContain('OBJECTSTORE_S3_KEY')
        ->and($auditPayload)->not->toContain('OBJECTSTORE_S3_SECRET')
        ->and($auditPayload)->not->toContain('AKIA');
});
