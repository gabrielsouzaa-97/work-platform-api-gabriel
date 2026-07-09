<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
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

function imageModeV1ApiKey(): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'ImageMode v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:write'],
        'allowed_tenant_slugs' => null,
    ]);

    return $rawToken;
}

function imageModeV1Bearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function mockImageModeProvisionSsh(string $jobId, ?callable $assertArgs = null): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (
            ClusterServer $cluster,
            string $cmd,
            array $args,
            ?string $payloadStdin = null,
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

function imageModeFlagCount(array $args): int
{
    return count(array_filter($args, fn (mixed $arg): bool => $arg === '--image-mode'));
}

it('POST /api/v1/tenants with image_mode true dispatches --image-mode once and persists customer flag', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 'imgmode-'.substr(uniqid(), -6);

    mockImageModeProvisionSsh(
        $jobId,
        fn (array $args): bool => imageModeFlagCount($args) === 1,
    );

    $rawToken = imageModeV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 'imgmode.example.com',
            'cluster_server_id' => $cluster->id,
            'image_mode' => true,
        ],
        imageModeV1Bearer($rawToken),
    );

    $response->assertStatus(202);
    $response->assertJsonPath('meta.job_id', $jobId);

    $customer = Customer::where('slug', $slug)->first();
    expect($customer)->not->toBeNull()
        ->and($customer->image_mode)->toBeTrue();
});

it('POST /api/v1/tenants without image_mode and config default false omits --image-mode and persists false', function (): void {
    config(['platform.image_mode.default_mode' => false]);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 'noimg-'.substr(uniqid(), -6);

    mockImageModeProvisionSsh(
        $jobId,
        fn (array $args): bool => ! in_array('--image-mode', $args, true),
    );

    $rawToken = imageModeV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 'noimg.example.com',
            'cluster_server_id' => $cluster->id,
            'suite_catalog' => false,
            'apps' => ['files'],
        ],
        imageModeV1Bearer($rawToken),
    );

    $response->assertStatus(202);

    $customer = Customer::where('slug', $slug)->first();
    expect($customer)->not->toBeNull()
        ->and($customer->image_mode)->toBeFalse();
});

it('POST /api/v1/tenants without image_mode uses config default true and dispatches --image-mode', function (): void {
    config(['platform.image_mode.default_mode' => true]);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 'imgdef-'.substr(uniqid(), -6);

    mockImageModeProvisionSsh(
        $jobId,
        fn (array $args): bool => imageModeFlagCount($args) === 1,
    );

    $rawToken = imageModeV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'domain' => 'imgdef.example.com',
            'cluster_server_id' => $cluster->id,
        ],
        imageModeV1Bearer($rawToken),
    );

    $response->assertStatus(202);

    $customer = Customer::where('slug', $slug)->first();
    expect($customer)->not->toBeNull()
        ->and($customer->image_mode)->toBeTrue();
});

it('POST /api/v1/tenants with suite catalog apps still dispatches --suite-catalog alongside --image-mode', function (): void {
    config([
        'platform.suite_catalog.default_mode' => true,
        'platform.image_mode.default_mode' => false,
    ]);

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();
    $slug = 'img-suite-'.substr(uniqid(), -6);

    mockImageModeProvisionSsh(
        $jobId,
        fn (array $args): bool => in_array('--suite-catalog', $args, true)
            && in_array('--apps=mail,dashboard,spreed', $args, true)
            && imageModeFlagCount($args) === 1,
    );

    $rawToken = imageModeV1ApiKey();

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'client' => $slug,
            'fqdn' => 'img-suite.example.com',
            'cluster_server_id' => $cluster->id,
            'shell' => true,
            'apps' => ['mail', 'dashboard', 'spreed'],
            'image_mode' => true,
        ],
        imageModeV1Bearer($rawToken),
    );

    $response->assertStatus(202);
    $response->assertJsonPath('meta.job_id', $jobId);
});
