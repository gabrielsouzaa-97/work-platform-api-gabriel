<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\FarmAgent;
use App\Models\FarmInventory;
use App\Modules\Dns\Actions\ProvisionDnsZoneAction;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.pdns.api_url' => 'https://pdns.test',
        'services.pdns.api_key' => 'pdns-test-key',
    ]);
});

function dnsProvisionCluster(string $sshHost = '203.0.113.50'): ClusterServer
{
    return ClusterServer::factory()->create([
        'status' => 'active',
        'ssh_host' => $sshHost,
    ]);
}

function seedDnsFarmInventory(ClusterServer $cluster, string $farmId = 'farm-dns'): FarmAgent
{
    $agent = FarmAgent::factory()->create([
        'farm_id' => $farmId,
        'cluster_server_id' => $cluster->id,
    ]);

    FarmInventory::create([
        'farm_id' => $farmId,
        'active_tenants' => 5,
        'max_tenants' => 100,
        'available_slots' => 95,
        'platform_version' => '1.0.0-rc.3',
        'latency_ms' => 20,
        'reported_at' => now(),
    ]);

    return $agent;
}

function dnsProvisionCustomer(ClusterServer $cluster, string $domain): Customer
{
    return Customer::create([
        'slug' => 'dns-'.substr(md5($domain), 0, 8),
        'cluster_server_id' => $cluster->id,
        'domain' => $domain,
        'status' => 'provisioning',
    ]);
}

function fakePdnsZoneProvision(string $domain): void
{
    $base = 'https://pdns.test/api/v1/servers/localhost/zones';
    Http::fake([
        $base => Http::response(['id' => "{$domain}."], 201),
        "{$base}/{$domain}." => Http::response(['rrsets' => []], 204),
    ]);
}

it('dns.zone.provision creates zone and required records for custom domain', function (): void {
    $cluster = dnsProvisionCluster();
    seedDnsFarmInventory($cluster);
    $domain = 'acme-custom.example.com';
    fakePdnsZoneProvision($domain);
    $customer = dnsProvisionCustomer($cluster, $domain);

    app(ProvisionDnsZoneAction::class)->execute($customer);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/zones'));
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains(json_encode($request->data()), 'mail.'.$domain));
});

it('dns.zone.provision is idempotent on second execution', function (): void {
    $cluster = dnsProvisionCluster();
    seedDnsFarmInventory($cluster);
    $domain = 'idempotent.example.com';
    fakePdnsZoneProvision($domain);
    $customer = dnsProvisionCustomer($cluster, $domain);
    $action = app(ProvisionDnsZoneAction::class);

    $action->execute($customer);
    $firstCount = count(Http::recorded());
    $action->execute($customer);

    expect(count(Http::recorded()))->toBe($firstCount);
});

it('dns.zone.provision points cloud mail and webmail records to farm target', function (): void {
    $targetIp = '203.0.113.99';
    $cluster = dnsProvisionCluster($targetIp);
    seedDnsFarmInventory($cluster);
    $domain = 'targets.example.com';
    fakePdnsZoneProvision($domain);
    $customer = dnsProvisionCustomer($cluster, $domain);

    app(ProvisionDnsZoneAction::class)->execute($customer);

    $payload = collect(Http::recorded())
        ->map(fn ($pair) => json_encode($pair[0]->data()))
        ->implode(' ');

    expect($payload)->toContain('cloud.'.$domain)
        ->and($payload)->toContain('mail.'.$domain)
        ->and($payload)->toContain('webmail.'.$domain)
        ->and($payload)->toContain($targetIp);
});

it('dns.zone.provision publishes SPF and DMARC TXT records', function (): void {
    $cluster = dnsProvisionCluster();
    seedDnsFarmInventory($cluster);
    $domain = 'deliver.example.com';
    fakePdnsZoneProvision($domain);
    $customer = dnsProvisionCustomer($cluster, $domain);

    app(ProvisionDnsZoneAction::class)->execute($customer);

    $payload = collect(Http::recorded())
        ->map(fn ($pair) => json_encode($pair[0]->data()))
        ->implode(' ');

    expect($payload)->toContain('v=spf1')
        ->and($payload)->toContain('v=DMARC1');
});
