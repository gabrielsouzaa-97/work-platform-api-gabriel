<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Onboarding;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Jobs\Services\WebhookHandler;
use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
use App\Modules\Onboarding\Saga\OnboardingSaga;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function makeOnboardingSagaCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function createOnboardingSagaApiKey(
    ?array $scopes = null,
    ?array $allowedTenantSlugs = null,
): string {
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Onboarding saga v1 test key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => $scopes,
        'allowed_tenant_slugs' => $allowedTenantSlugs,
    ]);

    return $rawToken;
}

function onboardingSagaBearer(string $rawToken, ?string $idempotencyKey = null): array
{
    $headers = ['Authorization' => "Bearer {$rawToken}"];

    if ($idempotencyKey !== null) {
        $headers['Idempotency-Key'] = $idempotencyKey;
    }

    return $headers;
}

function validOnboardingSagaPayload(ClusterServer $cluster, string $slug): array
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
        'apps_enabled' => ['calendar', 'deck'],
    ];
}

function mockOnboardingSagaSshAsync(string $jobId): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);
}

function assertOnboardingSagaAsyncEnvelope(TestResponse $response): string
{
    $response->assertStatus(202);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'status',
            'steps',
        ],
        'meta' => [
            'status_url',
        ],
    ]);

    $onboardingId = $response->json('data.id');
    expect($onboardingId)->toBeString()->not->toBeEmpty();
    expect($response->json('meta.status_url'))->toBeString()->toContain($onboardingId);

    return $onboardingId;
}

function assertOnboardingSagaStepStatus(TestResponse $response, string $onboardingId): void
{
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'id',
            'status',
            'steps' => [
                '*' => [
                    'name',
                    'status',
                ],
            ],
        ],
    ]);
    $response->assertJsonPath('data.id', $onboardingId);
    expect($response->json('data.steps'))->toBeArray()->not->toBeEmpty();
}

it('POST /api/v1/onboarding with valid payload and onboarding:run scope returns 202 with onboarding id', function (): void {
    $cluster = makeOnboardingSagaCluster();
    $slug = 'onboard-v1-'.substr(uniqid(), -6);
    mockOnboardingSagaSshAsync(Str::uuid()->toString());
    $rawToken = createOnboardingSagaApiKey(scopes: ['onboarding:run'], allowedTenantSlugs: [$slug]);

    $response = $this->postJson(
        '/api/v1/onboarding',
        validOnboardingSagaPayload($cluster, $slug),
        onboardingSagaBearer($rawToken),
    );

    assertOnboardingSagaAsyncEnvelope($response);
});

it('POST /api/v1/onboarding without onboarding:run scope returns 403 forbidden_scope', function (): void {
    $cluster = makeOnboardingSagaCluster();
    $slug = 'onboard-deny-'.substr(uniqid(), -6);
    $rawToken = createOnboardingSagaApiKey(scopes: ['tenants:read']);

    $response = $this->postJson(
        '/api/v1/onboarding',
        validOnboardingSagaPayload($cluster, $slug),
        onboardingSagaBearer($rawToken),
    );

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'forbidden_scope');
});

it('GET /api/v1/onboarding/{id} returns step-by-step status', function (): void {
    expect(Route::has('api.v1.onboarding.show'))->toBeTrue();

    $cluster = makeOnboardingSagaCluster();
    $slug = 'onboard-get-'.substr(uniqid(), -6);
    mockOnboardingSagaSshAsync(Str::uuid()->toString());
    $rawToken = createOnboardingSagaApiKey(scopes: ['onboarding:run'], allowedTenantSlugs: [$slug]);

    $create = $this->postJson(
        '/api/v1/onboarding',
        validOnboardingSagaPayload($cluster, $slug),
        onboardingSagaBearer($rawToken),
    );
    $onboardingId = assertOnboardingSagaAsyncEnvelope($create);

    $response = $this->getJson(
        "/api/v1/onboarding/{$onboardingId}",
        onboardingSagaBearer($rawToken),
    );

    assertOnboardingSagaStepStatus($response, $onboardingId);
});

it('POST /api/v1/onboarding replay with same Idempotency-Key returns same onboarding id without duplicate tenant', function (): void {
    $cluster = makeOnboardingSagaCluster();
    $slug = 'onboard-idem-'.substr(uniqid(), -6);
    $idempotencyKey = Str::uuid()->toString();
    mockOnboardingSagaSshAsync(Str::uuid()->toString());
    $rawToken = createOnboardingSagaApiKey(scopes: ['onboarding:run'], allowedTenantSlugs: [$slug]);
    $payload = validOnboardingSagaPayload($cluster, $slug);
    $headers = onboardingSagaBearer($rawToken, $idempotencyKey);

    $first = $this->postJson('/api/v1/onboarding', $payload, $headers);
    $firstId = assertOnboardingSagaAsyncEnvelope($first);

    $second = $this->postJson('/api/v1/onboarding', $payload, $headers);
    $secondId = assertOnboardingSagaAsyncEnvelope($second);

    expect($secondId)->toBe($firstId);
    expect(Customer::where('slug', $slug)->count())->toBe(1);
});

it('skipBrandingWhenBlocked marks set_branding step skipped with capability_not_available', function (): void {
    $onboarding = Onboarding::factory()->create([
        'state' => OnboardingState::Running,
        'current_step' => OnboardingStep::SetBranding,
        'steps' => [
            'provision_tenant' => ['status' => 'completed'],
            'set_branding' => ['status' => 'running'],
        ],
    ]);

    app(OnboardingSaga::class)->skipBrandingWhenBlocked($onboarding->fresh());

    $onboarding->refresh();
    expect($onboarding->state)->toBe(OnboardingState::Partial)
        ->and($onboarding->steps['set_branding']['status'])->toBe('skipped')
        ->and($onboarding->steps['set_branding']['reason'])->toBe('capability_not_available');
});

it('GET /api/v1/onboarding/{id} exposes skipped branding step in step list', function (): void {
    $onboarding = Onboarding::factory()->create([
        'state' => OnboardingState::Partial,
        'current_step' => OnboardingStep::SetBranding,
        'tenant_slug' => 'brand-skip-'.substr(uniqid(), -6),
        'steps' => [
            'set_branding' => [
                'status' => 'skipped',
                'reason' => 'capability_not_available',
            ],
        ],
    ]);
    $rawToken = createOnboardingSagaApiKey(
        scopes: ['onboarding:run'],
        allowedTenantSlugs: [$onboarding->tenant_slug],
    );

    $response = $this->getJson(
        "/api/v1/onboarding/{$onboarding->id}",
        onboardingSagaBearer($rawToken),
    );

    $response->assertOk();
    $response->assertJsonPath('data.status', 'partial');
    $steps = $response->json('data.steps');
    $brandingStep = collect($steps)->firstWhere('name', 'set_branding');
    expect($brandingStep)->not->toBeNull()
        ->and($brandingStep['status'])->toBe('skipped');
});

it('provision job failure marks onboarding state failed', function (): void {
    $cluster = makeOnboardingSagaCluster();
    $slug = 'onboard-fail-'.substr(uniqid(), -6);
    $provisionJobId = Str::uuid()->toString();
    mockOnboardingSagaSshAsync($provisionJobId);
    $rawToken = createOnboardingSagaApiKey(scopes: ['onboarding:run'], allowedTenantSlugs: [$slug]);

    $create = $this->postJson(
        '/api/v1/onboarding',
        validOnboardingSagaPayload($cluster, $slug),
        onboardingSagaBearer($rawToken),
    );
    $onboardingId = assertOnboardingSagaAsyncEnvelope($create);

    $noop = Mockery::mock(SshClientInterface::class);
    $noop->shouldReceive('run')->andReturn(new SshResponse(stdout: '[]', stderr: '', exitCode: 0, parsedJson: []));
    app()->instance(SshClientInterface::class, $noop);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $provisionJobId,
        'state' => 'failed',
        'exit_code' => 1,
    ]);

    $onboarding = Onboarding::query()->findOrFail($onboardingId);
    expect($onboarding->state)->toBe(OnboardingState::Failed)
        ->and($onboarding->steps['provision_tenant']['status'])->toBe('failed');
});
