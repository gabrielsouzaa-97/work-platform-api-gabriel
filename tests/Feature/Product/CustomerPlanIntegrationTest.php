<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\Plan;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function planIntegrationCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function planIntegrationOperator(): Operator
{
    return Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
}

function seedProPlan(): Plan
{
    return Plan::create([
        'slug' => 'pro',
        'name' => 'Pro',
        'default_quota' => '20 GB',
        'is_default' => false,
        'status' => 'active',
    ]);
}

function mockPlanProvisionSsh(string $jobId): void
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

function createTenantPlanApiKey(): string
{
    $admin = Operator::factory()->admin()->create();
    $rawToken = 'sk_'.bin2hex(random_bytes(32));

    ApiKey::create([
        'id' => Str::uuid()->toString(),
        'operator_id' => $admin->id,
        'name' => 'Tenant plan integration key',
        'token_hash' => hash('sha256', $rawToken),
        'scopes' => ['tenants:write', 'users:write'],
    ]);

    return $rawToken;
}

it('PlanSeeder creates default plan with 5 GB quota', function (): void {
    $this->seed(PlanSeeder::class);

    $default = Plan::find('default');

    expect($default)->not->toBeNull()
        ->and($default?->default_quota)->toBe('5 GB')
        ->and($default?->is_default)->toBeTrue();
});

it('POST /api/customers persists plan_slug foreign key', function (): void {
    $cluster = planIntegrationCluster();
    $operator = planIntegrationOperator();
    seedProPlan();
    $jobId = Str::uuid()->toString();
    $slug = 'plan-co-'.substr(uniqid(), -6);

    mockPlanProvisionSsh($jobId);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'plan_slug' => 'pro',
    ]);

    $response->assertStatus(201);
    expect(Customer::find($slug)?->plan_slug)->toBe('pro');
});

it('POST /api/v1/tenants persists plan_slug foreign key', function (): void {
    $cluster = planIntegrationCluster();
    seedProPlan();
    $jobId = Str::uuid()->toString();
    $slug = 'v1-plan-'.substr(uniqid(), -6);
    $rawToken = createTenantPlanApiKey();

    mockPlanProvisionSsh($jobId);

    $response = $this->postJson(
        '/api/v1/tenants',
        [
            'slug' => $slug,
            'cluster_server_id' => $cluster->id,
            'domain' => "{$slug}.example.com",
            'plan_slug' => 'pro',
        ],
        ['Authorization' => "Bearer {$rawToken}"],
    );

    $response->assertStatus(202);
    expect(Customer::find($slug)?->plan_slug)->toBe('pro');
});

it('POST users without explicit quota inherits plan default_quota in upstream stdin', function (): void {
    $cluster = planIntegrationCluster();
    $operator = planIntegrationOperator();
    seedProPlan();
    $customer = Customer::create([
        'slug' => 'quota-inherit-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'quota-inherit.example.com',
        'status' => 'active',
        'plan_slug' => 'pro',
    ]);
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) use ($customer): bool {
            $decoded = json_decode($stdin ?? '', true);

            return is_array($decoded)
                && ($decoded['quota'] ?? null) === '20 GB';
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
        ])
        ->assertStatus(202);
});

it('rejects unknown plan_slug on tenant provision with 422', function (): void {
    $cluster = planIntegrationCluster();
    $operator = planIntegrationOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson('/api/customers', [
            'slug' => 'bad-plan-'.substr(uniqid(), -6),
            'cluster_server_id' => $cluster->id,
            'domain' => 'bad-plan.example.com',
            'plan_slug' => 'missing-plan',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_slug']);
});
