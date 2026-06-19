<?php

declare(strict_types=1);

use App\Models\AgentCommand;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\FarmAgent;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Mail\Actions\ProvisionTenantMailAction;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Config::set('services.agent.transport_enabled', true);
});

function mailPipelineCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function mailPipelineCustomer(ClusterServer $cluster, string $slug = 'mail-hook'): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'provisioning',
    ]);
}

function mailPipelineAuthHeaders(FarmAgent $agent): array
{
    return [
        'Authorization' => 'Bearer test-agent-token',
        'X-Farm-Id' => $agent->farm_id,
        'Accept' => 'application/json',
    ];
}

function fakeMailApiProvisionResponses(string $domain): void
{
    Http::fake([
        'https://mail-api.test/v1/domains' => Http::response(['domain' => $domain], 201),
        'https://mail-api.test/v1/mailboxes' => Http::response([
            'address' => "admin@{$domain}",
        ], 201),
    ]);
}

it('ProvisionTenantMailAction creates domain and admin mailbox via mail API', function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'pipeline-key',
    ]);
    fakeMailApiProvisionResponses('acme.example.com');

    $customer = mailPipelineCustomer(mailPipelineCluster(), 'acme-mail');

    app(ProvisionTenantMailAction::class)->execute(
        customer: $customer,
        adminEmail: 'admin@acme.example.com',
        adminPassword: 'Secret123!',
    );

    Http::assertSentCount(2);
    expect(AuditLog::where('action', 'mail_provisioned')
        ->where('resource_id', $customer->slug)
        ->exists())->toBeTrue();
});

it('agent containers_up event triggers mail provisioning for tenant.create', function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'pipeline-key',
    ]);

    $cluster = mailPipelineCluster();
    $agent = FarmAgent::factory()->create(['cluster_server_id' => $cluster->id]);
    $customer = mailPipelineCustomer($cluster, 'agent-mail');
    $job = Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => "nextcloud-manage {$customer->slug} _ provision",
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now(),
    ]);
    $command = AgentCommand::create([
        'farm_agent_id' => $agent->id,
        'operation_id' => Str::uuid()->toString(),
        'operation' => 'tenant.create',
        'status' => 'pending',
        'requested_at' => now(),
        'payload' => [
            'mail' => [
                'provision_domain' => true,
                'default_mailbox' => "admin@{$customer->domain}",
            ],
        ],
    ]);

    fakeMailApiProvisionResponses($customer->domain);

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation_id' => $command->operation_id,
        'farm_id' => $agent->farm_id,
        'state' => 'running',
        'step' => 'containers_up',
        'data' => ['job_id' => $job->job_id],
        'ts' => now()->toIso8601String(),
    ], mailPipelineAuthHeaders($agent))->assertAccepted();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/domains'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/mailboxes'));
});

it('POST customers with mail payload stores mail options for later hook execution', function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'pipeline-key',
        'services.agent.transport_enabled' => false,
    ]);

    $cluster = mailPipelineCluster();
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $sshMock);

    $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'feature-mail',
        'cluster_server_id' => $cluster->id,
        'domain' => 'feature-mail.example.com',
        'mail' => [
            'provision_domain' => true,
            'default_mailbox' => 'admin@feature-mail.example.com',
        ],
    ])->assertStatus(201);

    $customer = Customer::find('feature-mail');
    expect($customer)->not->toBeNull()
        ->and($customer->mail_provision_payload)->toMatchArray([
            'provision_domain' => true,
            'default_mailbox' => 'admin@feature-mail.example.com',
        ]);
});
