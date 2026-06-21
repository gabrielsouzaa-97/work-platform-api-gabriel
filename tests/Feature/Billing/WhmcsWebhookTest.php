<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Cache::flush();

    config([
        'whmcs.enabled' => true,
        'whmcs.webhook_secret' => 'whmcs-webhook-secret',
    ]);

    Operator::factory()->create(['role' => 'admin', 'status' => 'active']);
});

function whmcsSignature(string $body, string $secret = 'whmcs-webhook-secret'): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

function postWhmcsWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call('POST', '/api/webhooks/whmcs', [], [], [], [
        'HTTP_X_WHMCS_SIGNATURE' => $signature ?? whmcsSignature($body),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}

it('rejects webhook with invalid signature', function (): void {
    $payload = ['event' => 'InvoicePaid', 'invoice_id' => 1];

    postWhmcsWebhook($payload, 'sha256=invalid')->assertStatus(401)
        ->assertJson(['error' => 'invalid_signature']);
});

it('InvoicePaid triggers provision path', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $sshMock);

    $response = postWhmcsWebhook([
        'event' => 'InvoicePaid',
        'invoice_id' => 100,
        'service_id' => 55,
        'tenant_slug' => 'billing-acme',
        'domain' => 'billing-acme.example.com',
        'cluster_server_id' => $cluster->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'provisioned',
            'tenant_slug' => 'billing-acme',
            'job_id' => $jobId,
        ]);

    expect(Customer::find('billing-acme'))->not->toBeNull();
});

it('ModuleSuspend sets customer suspended', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'suspend-me',
        'cluster_server_id' => $cluster->id,
        'domain' => 'suspend-me.example.com',
        'status' => CustomerLifecycleStatus::ACTIVE,
    ]);

    $jobId = Str::uuid()->toString();
    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $sshMock);

    $response = postWhmcsWebhook([
        'event' => 'ModuleSuspend',
        'service_id' => 77,
        'tenant_slug' => $customer->slug,
    ]);

    $response->assertOk()->assertJson([
        'status' => 'suspended',
        'tenant_slug' => $customer->slug,
        'job_id' => $jobId,
    ]);

    expect($customer->fresh()->status)->toBe('suspended');
});

it('TrialExpired suspends tenant', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'trial-expired',
        'cluster_server_id' => $cluster->id,
        'domain' => 'trial-expired.example.com',
        'status' => CustomerLifecycleStatus::ACTIVE,
    ]);

    $jobId = Str::uuid()->toString();
    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $sshMock);

    postWhmcsWebhook([
        'event' => 'TrialExpired',
        'service_id' => 88,
        'tenant_slug' => $customer->slug,
    ])->assertOk()->assertJson(['status' => 'suspended']);

    expect($customer->fresh()->status)->toBe('suspended');
});

it('duplicate webhook by invoice_id is idempotent', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $sshMock);

    $payload = [
        'event' => 'InvoicePaid',
        'invoice_id' => 999,
        'tenant_slug' => 'once-only',
        'domain' => 'once-only.example.com',
        'cluster_server_id' => $cluster->id,
    ];

    postWhmcsWebhook($payload)->assertOk()->assertJson(['status' => 'provisioned']);
    postWhmcsWebhook($payload)->assertOk()->assertJson(['status' => 'duplicate']);
});
