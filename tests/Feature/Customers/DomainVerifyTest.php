<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Dns\Contracts\DnsLookupServiceInterface;
use App\Modules\Dns\Dto\DnsLookupResult;

function domainVerifyCustomer(string $domain = 'verify.example.com'): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    return Customer::create([
        'slug' => 'verify-'.substr(md5($domain), 0, 8),
        'cluster_server_id' => $cluster->id,
        'domain' => $domain,
        'status' => 'provisioning',
    ]);
}

function domainVerifyOperator(): Operator
{
    return Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
}

function bindDnsLookupMock(array $overrides = []): void
{
    $defaults = [
        'mx' => ['10 mail.verify.example.com'],
        'spf' => ['v=spf1 a:mail.verify.example.com -all'],
        'dkim' => ['v=DKIM1; k=rsa; p=test'],
        'dmarc' => ['v=DMARC1; p=none'],
        'zone_managed' => true,
    ];
    $data = array_merge($defaults, $overrides);

    $mock = Mockery::mock(DnsLookupServiceInterface::class);
    $mock->shouldReceive('lookup')->andReturn(new DnsLookupResult(
        mx: $data['mx'],
        spf: $data['spf'],
        dkim: $data['dkim'],
        dmarc: $data['dmarc'],
        zoneManaged: $data['zone_managed'],
    ));
    app()->instance(DnsLookupServiceInterface::class, $mock);
}

it('POST domain verify returns verified when MX SPF DKIM DMARC match expected', function (): void {
    bindDnsLookupMock();
    $customer = domainVerifyCustomer();
    $operator = domainVerifyOperator();

    $response = $this->actingAs($operator)->postJson(
        "/api/customers/{$customer->slug}/domain/verify",
    );

    $response->assertOk()
        ->assertJsonPath('status', 'verified')
        ->assertJsonStructure(['checks' => ['mx', 'spf', 'dkim', 'dmarc']]);
});

it('POST domain verify returns failed when MX record mismatches expected', function (): void {
    bindDnsLookupMock(['mx' => ['20 wrong.mail.example.com']]);
    $customer = domainVerifyCustomer();
    $operator = domainVerifyOperator();

    $response = $this->actingAs($operator)->postJson(
        "/api/customers/{$customer->slug}/domain/verify",
    );

    $response->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('checks.mx', false);
});

it('POST domain verify returns manual records JSON for external DNS', function (): void {
    bindDnsLookupMock(['zone_managed' => false]);
    $customer = domainVerifyCustomer();
    $operator = domainVerifyOperator();

    $response = $this->actingAs($operator)->postJson(
        "/api/customers/{$customer->slug}/domain/verify",
    );

    $response->assertOk()
        ->assertJsonPath('status', 'pending_manual')
        ->assertJsonStructure(['records' => [['type', 'name', 'content']]]);
});

it('POST domain verify requires authenticated operator', function (): void {
    $customer = domainVerifyCustomer();

    $this->postJson("/api/customers/{$customer->slug}/domain/verify")
        ->assertUnauthorized();
});

it('POST domain verify returns 404 for unknown customer slug', function (): void {
    $operator = domainVerifyOperator();

    $this->actingAs($operator)->postJson('/api/customers/missing-slug/domain/verify')
        ->assertNotFound();
});
