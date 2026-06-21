<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\FarmAgent;
use App\Models\FarmInventory;
use App\Modules\Dns\Actions\PublishDkimRecordAction;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.pdns.api_url' => 'https://pdns.test',
        'services.pdns.api_key' => 'pdns-test-key',
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'mail-test-key',
    ]);
});

function dkimPublishCustomer(string $domain): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active', 'ssh_host' => '203.0.113.10']);
    FarmAgent::factory()->create(['farm_id' => 'farm-dkim', 'cluster_server_id' => $cluster->id]);
    FarmInventory::create([
        'farm_id' => 'farm-dkim',
        'active_tenants' => 1,
        'max_tenants' => 50,
        'available_slots' => 49,
        'platform_version' => '1.0.0-rc.3',
        'latency_ms' => 10,
        'reported_at' => now(),
    ]);

    return Customer::create([
        'slug' => 'dkim-'.substr(md5($domain), 0, 8),
        'cluster_server_id' => $cluster->id,
        'domain' => $domain,
        'status' => 'provisioning',
    ]);
}

function fakeDkimCrossApi(string $domain, string $selector = 'default'): void
{
    $dkimTxt = 'v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtest';
    Http::fake([
        "https://mail-api.test/v1/domains/{$domain}/dkim" => Http::response([
            'selector' => $selector,
            'public_key' => $dkimTxt,
            'record_name' => "{$selector}._domainkey.{$domain}",
        ], 200),
        'https://pdns.test/api/v1/servers/localhost/zones/'.$domain.'.' => Http::response(['rrsets' => []], 204),
    ]);
}

it('fetches DKIM from mail-api and publishes TXT in PowerDNS zone', function (): void {
    $domain = 'dkim-publish.example.com';
    fakeDkimCrossApi($domain);
    $customer = dkimPublishCustomer($domain);

    app(PublishDkimRecordAction::class)->execute($customer);

    Http::assertSent(fn ($request) => str_contains($request->url(), "/v1/domains/{$domain}/dkim"));
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains(json_encode($request->data()), '_domainkey')
        && str_contains(json_encode($request->data()), 'v=DKIM1'));
});

it('publish DKIM is idempotent when TXT record already exists', function (): void {
    $domain = 'dkim-idempotent.example.com';
    fakeDkimCrossApi($domain);
    $customer = dkimPublishCustomer($domain);
    $action = app(PublishDkimRecordAction::class);

    $action->execute($customer);
    $firstCount = count(Http::recorded());
    $action->execute($customer);

    expect(count(Http::recorded()))->toBeLessThanOrEqual($firstCount + 1);
});
