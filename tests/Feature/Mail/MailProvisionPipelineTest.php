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
    return ClusterServer::factory()->create([
        'status' => 'active',
        'ssh_host' => '203.0.113.50',
    ]);
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
        'services.pdns.api_url' => 'https://pdns.test',
        'services.pdns.api_key' => 'pdns-test-key',
    ]);

    $cluster = mailPipelineCluster();
    $agent = FarmAgent::factory()->create(['cluster_server_id' => $cluster->id]);
    $customer = mailPipelineCustomer($cluster, 'agent-mail');
    $customer->update([
        'mail_provision_payload' => [
            'provision_domain' => true,
            'default_mailbox' => "admin@{$customer->domain}",
        ],
    ]);
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
        'payload' => [],
    ]);

    $pdnsBase = 'https://pdns.test/api/v1/servers/localhost/zones';
    $pdnsZoneUrl = "{$pdnsBase}/{$customer->domain}.";
    Http::fake([
        'https://mail-api.test/v1/domains' => Http::response(['domain' => $customer->domain], 201),
        'https://mail-api.test/v1/mailboxes' => Http::response([
            'address' => "admin@{$customer->domain}",
        ], 201),
        "https://mail-api.test/v1/domains/{$customer->domain}/dkim" => Http::response([
            'selector' => 'default',
            'public_key' => 'v=DKIM1; k=rsa; p=test',
            'record_name' => "default._domainkey.{$customer->domain}",
        ], 200),
        $pdnsBase => Http::response(['id' => "{$customer->domain}."], 201),
        $pdnsZoneUrl => Http::sequence()
            ->push(['error' => 'not_found'], 404)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204),
    ]);

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
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/zones'));

    $customer->refresh();
    expect($customer->branding_meta['mail_admin_password_encrypted'] ?? null)->not->toBeNull()
        ->and($customer->branding_meta['mail_provisioned'] ?? false)->toBeTrue();
});

it('agent containers_up persists provided admin password encrypted before mail API call', function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'pipeline-key',
        'services.pdns.api_url' => 'https://pdns.test',
        'services.pdns.api_key' => 'pdns-test-key',
    ]);

    $cluster = mailPipelineCluster();
    $agent = FarmAgent::factory()->create(['cluster_server_id' => $cluster->id]);
    $customer = mailPipelineCustomer($cluster, 'pwd-mail');
    $customer->update([
        'mail_provision_payload' => [
            'provision_domain' => true,
            'default_mailbox' => "admin@{$customer->domain}",
            'admin_password' => 'ProvidedSecret1!',
        ],
    ]);
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
        'payload' => [],
    ]);

    $pdnsBase = 'https://pdns.test/api/v1/servers/localhost/zones';
    $pdnsZoneUrl = "{$pdnsBase}/{$customer->domain}.";
    Http::fake([
        'https://mail-api.test/v1/domains' => Http::response(['domain' => $customer->domain], 201),
        'https://mail-api.test/v1/mailboxes' => Http::response([
            'address' => "admin@{$customer->domain}",
        ], 201),
        "https://mail-api.test/v1/domains/{$customer->domain}/dkim" => Http::response([
            'selector' => 'default',
            'public_key' => 'v=DKIM1; k=rsa; p=test',
            'record_name' => "default._domainkey.{$customer->domain}",
        ], 200),
        $pdnsBase => Http::response(['id' => "{$customer->domain}."], 201),
        $pdnsZoneUrl => Http::sequence()
            ->push(['error' => 'not_found'], 404)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204),
    ]);

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation_id' => $command->operation_id,
        'farm_id' => $agent->farm_id,
        'state' => 'running',
        'step' => 'containers_up',
        'data' => ['job_id' => $job->job_id],
        'ts' => now()->toIso8601String(),
    ], mailPipelineAuthHeaders($agent))->assertAccepted();

    $customer->refresh();
    expect(decrypt($customer->branding_meta['mail_admin_password_encrypted']))->toBe('ProvidedSecret1!');
});

it('agent containers_up still returns 202 when mail API fails', function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'pipeline-key',
    ]);

    $cluster = mailPipelineCluster();
    $agent = FarmAgent::factory()->create(['cluster_server_id' => $cluster->id]);
    $customer = mailPipelineCustomer($cluster, 'fail-mail');
    $customer->update([
        'mail_provision_payload' => [
            'provision_domain' => true,
            'default_mailbox' => "admin@{$customer->domain}",
        ],
    ]);
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
        'payload' => [],
    ]);

    Http::fake([
        'https://mail-api.test/v1/domains' => Http::response(['error' => 'upstream'], 502),
    ]);

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation_id' => $command->operation_id,
        'farm_id' => $agent->farm_id,
        'state' => 'running',
        'step' => 'containers_up',
        'data' => ['job_id' => $job->job_id],
        'ts' => now()->toIso8601String(),
    ], mailPipelineAuthHeaders($agent))->assertAccepted();

    expect(AuditLog::where('action', 'mail_provision_failed')
        ->where('resource_id', $customer->slug)
        ->exists())->toBeTrue();
});

it('ProvisionTenantMailAction skips when mail already provisioned', function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'pipeline-key',
    ]);

    $customer = mailPipelineCustomer(mailPipelineCluster(), 'idempotent-mail');
    $customer->update(['branding_meta' => ['mail_provisioned' => true]]);

    Http::fake();

    app(ProvisionTenantMailAction::class)->execute(
        customer: $customer,
        adminEmail: 'admin@idempotent-mail.example.com',
        adminPassword: 'Secret123!',
    );

    Http::assertNothingSent();
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

it('agent containers_up still returns 202 when PDNS fails after mail success', function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'pipeline-key',
        'services.pdns.api_url' => 'https://pdns.test',
        'services.pdns.api_key' => 'pdns-test-key',
    ]);

    $cluster = mailPipelineCluster();
    $agent = FarmAgent::factory()->create(['cluster_server_id' => $cluster->id]);
    $customer = mailPipelineCustomer($cluster, 'pdns-fail-mail');
    $customer->update([
        'mail_provision_payload' => [
            'provision_domain' => true,
            'default_mailbox' => "admin@{$customer->domain}",
        ],
    ]);
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
        'payload' => [],
    ]);

    $pdnsBase = 'https://pdns.test/api/v1/servers/localhost/zones';
    $pdnsZoneUrl = "{$pdnsBase}/{$customer->domain}.";
    Http::fake([
        'https://mail-api.test/v1/domains' => Http::response(['domain' => $customer->domain], 201),
        'https://mail-api.test/v1/mailboxes' => Http::response([
            'address' => "admin@{$customer->domain}",
        ], 201),
        $pdnsZoneUrl => Http::response(['error' => 'not_found'], 404),
        $pdnsBase => Http::response(['error' => 'upstream'], 502),
    ]);

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation_id' => $command->operation_id,
        'farm_id' => $agent->farm_id,
        'state' => 'running',
        'step' => 'containers_up',
        'data' => ['job_id' => $job->job_id],
        'ts' => now()->toIso8601String(),
    ], mailPipelineAuthHeaders($agent))->assertAccepted();

    expect(AuditLog::where('action', 'mail_provisioned')
        ->where('resource_id', $customer->slug)
        ->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'dns_provision_failed')
            ->where('resource_id', $customer->slug)
            ->exists())->toBeTrue();
});

it('duplicate containers_up does not rotate encrypted mail password', function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'pipeline-key',
        'services.pdns.api_url' => 'https://pdns.test',
        'services.pdns.api_key' => 'pdns-test-key',
    ]);

    $cluster = mailPipelineCluster();
    $agent = FarmAgent::factory()->create(['cluster_server_id' => $cluster->id]);
    $customer = mailPipelineCustomer($cluster, 'retry-mail');
    $customer->update([
        'mail_provision_payload' => [
            'provision_domain' => true,
            'default_mailbox' => "admin@{$customer->domain}",
            'admin_password' => 'StablePassword1!',
        ],
    ]);
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
        'payload' => [],
    ]);

    $pdnsBase = 'https://pdns.test/api/v1/servers/localhost/zones';
    $pdnsZoneUrl = "{$pdnsBase}/{$customer->domain}.";
    Http::fake([
        'https://mail-api.test/v1/domains' => Http::response(['domain' => $customer->domain], 201),
        'https://mail-api.test/v1/mailboxes' => Http::response([
            'address' => "admin@{$customer->domain}",
        ], 201),
        "https://mail-api.test/v1/domains/{$customer->domain}/dkim" => Http::response([
            'selector' => 'default',
            'public_key' => 'v=DKIM1; k=rsa; p=test',
            'record_name' => "default._domainkey.{$customer->domain}",
        ], 200),
        $pdnsBase => Http::response(['id' => "{$customer->domain}."], 201),
        $pdnsZoneUrl => Http::sequence()
            ->push(['error' => 'not_found'], 404)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204)
            ->push(['rrsets' => []], 204),
    ]);

    $eventPayload = [
        'schema_version' => 1,
        'operation_id' => $command->operation_id,
        'farm_id' => $agent->farm_id,
        'state' => 'running',
        'step' => 'containers_up',
        'data' => ['job_id' => $job->job_id],
        'ts' => now()->toIso8601String(),
    ];

    $this->postJson('/api/agent/v1/events', $eventPayload, mailPipelineAuthHeaders($agent))
        ->assertAccepted();

    $customer->refresh();
    $firstEncrypted = $customer->branding_meta['mail_admin_password_encrypted'] ?? null;
    expect($firstEncrypted)->not->toBeNull()
        ->and(decrypt($firstEncrypted))->toBe('StablePassword1!');

    Http::fake([
        'https://mail-api.test/*' => Http::response(['error' => 'should not call'], 500),
        'https://pdns.test/*' => Http::response(['rrsets' => []], 204),
    ]);

    $this->postJson('/api/agent/v1/events', $eventPayload, mailPipelineAuthHeaders($agent))
        ->assertAccepted();

    $customer->refresh();
    $secondEncrypted = $customer->branding_meta['mail_admin_password_encrypted'] ?? null;
    expect($secondEncrypted)->toBe($firstEncrypted)
        ->and(decrypt($secondEncrypted))->toBe('StablePassword1!');

    Http::assertNothingSent();
});
