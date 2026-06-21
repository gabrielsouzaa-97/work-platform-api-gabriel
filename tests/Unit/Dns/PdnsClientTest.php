<?php

declare(strict_types=1);

use App\Modules\Dns\Exceptions\PdnsException;
use App\Modules\Dns\Services\PdnsClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.pdns.api_url' => 'https://pdns.test',
        'services.pdns.api_key' => 'pdns-test-key',
    ]);
});

function pdnsClient(): PdnsClient
{
    return app(PdnsClient::class);
}

function pdnsApiKeyHeader(): string
{
    return 'pdns-test-key';
}

function pdnsZonesUrl(): string
{
    return 'https://pdns.test/api/v1/servers/localhost/zones';
}

it('creates zone via POST /api/v1/servers/localhost/zones with api key', function (): void {
    Http::fake([
        pdnsZonesUrl() => Http::response(['id' => 'acme.example.com.'], 201),
    ]);

    $result = pdnsClient()->createZone('acme.example.com');

    expect($result)->toMatchArray(['id' => 'acme.example.com.']);

    Http::assertSent(fn ($request) => $request->url() === pdnsZonesUrl()
        && $request->method() === 'POST'
        && $request->hasHeader('X-API-Key', pdnsApiKeyHeader()));
});

it('upserts A record in zone via PATCH rrsets', function (): void {
    $zoneUrl = pdnsZonesUrl().'/acme.example.com.';
    Http::fake([
        $zoneUrl => Http::response(['rrsets' => []], 204),
    ]);

    pdnsClient()->upsertRecord(
        zone: 'acme.example.com',
        name: 'cloud.acme.example.com',
        type: 'A',
        content: '203.0.113.10',
        ttl: 300,
    );

    Http::assertSent(fn ($request) => $request->url() === $zoneUrl
        && $request->method() === 'PATCH'
        && str_contains(json_encode($request->data()), '"type":"A"')
        && str_contains(json_encode($request->data()), '203.0.113.10'));
});

it('upserts MX record in zone via PATCH rrsets', function (): void {
    $zoneUrl = pdnsZonesUrl().'/acme.example.com.';
    Http::fake([$zoneUrl => Http::response(['rrsets' => []], 204)]);

    pdnsClient()->upsertRecord(
        zone: 'acme.example.com',
        name: 'acme.example.com',
        type: 'MX',
        content: '10 mail.acme.example.com',
        ttl: 300,
    );

    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), '"type":"MX"')
        && str_contains(json_encode($request->data()), 'mail.acme.example.com'));
});

it('upserts TXT record for SPF in zone via PATCH rrsets', function (): void {
    $zoneUrl = pdnsZonesUrl().'/acme.example.com.';
    Http::fake([$zoneUrl => Http::response(['rrsets' => []], 204)]);

    pdnsClient()->upsertRecord(
        zone: 'acme.example.com',
        name: 'acme.example.com',
        type: 'TXT',
        content: 'v=spf1 a:mail.acme.example.com -all',
        ttl: 300,
    );

    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), '"type":"TXT"')
        && str_contains(json_encode($request->data()), 'v=spf1'));
});

it('lists zone records via GET zone endpoint', function (): void {
    $zoneUrl = pdnsZonesUrl().'/acme.example.com.';
    Http::fake([
        $zoneUrl => Http::response([
            'id' => 'acme.example.com.',
            'rrsets' => [['name' => 'acme.example.com.', 'type' => 'SOA']],
        ], 200),
    ]);

    $zone = pdnsClient()->getZone('acme.example.com');

    expect($zone['id'])->toBe('acme.example.com.')
        ->and($zone['rrsets'])->toHaveCount(1);
});

it('throws PdnsException when zone creation fails', function (): void {
    Http::fake([
        pdnsZonesUrl() => Http::response(['error' => 'zone_exists'], 409),
    ]);

    pdnsClient()->createZone('taken.example.com');
})->throws(PdnsException::class);

it('retries transient 503 responses before succeeding', function (): void {
    Http::fake([
        pdnsZonesUrl() => Http::sequence()
            ->push(['error' => 'unavailable'], 503)
            ->push(['id' => 'retry.example.com.'], 201),
    ]);

    $result = pdnsClient()->createZone('retry.example.com');

    expect($result['id'])->toBe('retry.example.com.');
    Http::assertSentCount(2);
});

it('ensureZoneExists skips create when zone already exists', function (): void {
    $zoneUrl = pdnsZonesUrl().'/existing.example.com.';
    Http::fake([
        $zoneUrl => Http::response(['id' => 'existing.example.com.'], 200),
    ]);

    pdnsClient()->ensureZoneExists('existing.example.com');

    Http::assertSent(fn ($request) => $request->method() === 'GET');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('ensureZoneExists treats createZone 409 as success and continues', function (): void {
    Http::fake([
        pdnsZonesUrl().'/conflict.example.com.' => Http::response(['error' => 'not_found'], 404),
        pdnsZonesUrl() => Http::response(['error' => 'zone_exists'], 409),
    ]);

    pdnsClient()->ensureZoneExists('conflict.example.com');

    Http::assertSent(fn ($request) => $request->method() === 'GET');
    Http::assertSent(fn ($request) => $request->method() === 'POST');
});
