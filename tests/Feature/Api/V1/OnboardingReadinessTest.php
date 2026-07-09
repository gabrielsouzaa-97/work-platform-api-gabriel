<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Onboarding;
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

function onboardingReadinessApiKey(string $slug): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Onboarding readiness v1 key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['onboarding:run'],
        'allowed_tenant_slugs' => [$slug],
    ]);

    return $rawToken;
}

function onboardingReadinessBearer(string $rawToken): array
{
    return ['Authorization' => "Bearer {$rawToken}"];
}

function legacyImpossibleOnboardingPayload(ClusterServer $cluster, string $slug): array
{
    return [
        'tenant' => [
            'slug' => $slug,
            'cluster_server_id' => $cluster->id,
            'domain' => "{$slug}.example.com",
        ],
        'admin' => [
            'username' => 'admin.'.$slug,
            'password' => 'Secret123!',
            'email' => "admin@{$slug}.example.com",
        ],
        'apps_enabled' => ['mail'],
    ];
}

function assertOnboardingLegacyReadinessViolation(TestResponse $response): void
{
    $response->assertStatus(422);

    $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
    expect($encoded)
        ->toContain('LEGACY_READINESS_UNSATISFIABLE')
        ->and($encoded)->toMatch('/image_mode/i');
}

it('POST /api/v1/onboarding without image_mode on legacy impossible apps returns 422', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $slug = 'onboard-legacy-'.substr(uniqid(), -6);
    $rawToken = onboardingReadinessApiKey($slug);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->postJson(
        '/api/v1/onboarding',
        legacyImpossibleOnboardingPayload($cluster, $slug),
        onboardingReadinessBearer($rawToken),
    );

    assertOnboardingLegacyReadinessViolation($response);
    expect(Onboarding::count())->toBe(0);
});

it('POST /api/v1/onboarding with tenant image_mode true is accepted', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $slug = 'onboard-img-'.substr(uniqid(), -6);
    $rawToken = onboardingReadinessApiKey($slug);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->andReturn(
        new SshResponse(
            stdout: json_encode(['job_id' => Str::uuid()->toString()]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => Str::uuid()->toString()],
        ),
    );
    app()->instance(SshClientInterface::class, $ssh);

    $payload = legacyImpossibleOnboardingPayload($cluster, $slug);
    $payload['tenant']['image_mode'] = true;

    $response = $this->postJson(
        '/api/v1/onboarding',
        $payload,
        onboardingReadinessBearer($rawToken),
    );

    $response->assertStatus(202);
    expect(Onboarding::count())->toBe(1);
});
