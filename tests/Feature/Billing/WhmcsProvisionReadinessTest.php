<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Cache::flush();

    config([
        'whmcs.enabled' => true,
        'whmcs.webhook_secret' => 'whmcs-webhook-secret',
        'platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog.json'),
        'platform.suite_catalog.default_mode' => true,
        'platform.image_mode.default_mode' => false,
    ]);

    Operator::factory()->create(['role' => 'admin', 'status' => 'active']);
});

function whmcsReadinessSignature(string $body, string $secret = 'whmcs-webhook-secret'): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

function postWhmcsReadinessWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call('POST', '/api/webhooks/whmcs', [], [], [], [
        'HTTP_X_WHMCS_SIGNATURE' => $signature ?? whmcsReadinessSignature($body),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}

it('InvoicePaid with condemned legacy defaults rejects before provision', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    $response = postWhmcsReadinessWebhook([
        'event' => 'InvoicePaid',
        'invoice_id' => 501,
        'service_id' => 77,
        'tenant_slug' => 'whmcs-condemned',
        'domain' => 'whmcs-condemned.example.com',
        'cluster_server_id' => $cluster->id,
    ]);

    $response->assertStatus(422);

    $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
    expect($encoded)
        ->toContain('LEGACY_READINESS_UNSATISFIABLE')
        ->and($encoded)->toMatch('/image_mode/i');

    expect(Customer::find('whmcs-condemned'))->toBeNull()
        ->and(Job::count())->toBe(0);
});
