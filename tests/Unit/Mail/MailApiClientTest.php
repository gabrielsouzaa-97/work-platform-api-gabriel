<?php

declare(strict_types=1);

use App\Modules\Mail\Exceptions\MailApiException;
use App\Modules\Mail\Services\MailApiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'mail_api.base_url' => 'https://mail-api.test',
        'mail_api.api_key' => 'test-api-key',
    ]);
});

function mailApiClient(): MailApiClient
{
    return app(MailApiClient::class);
}

function mailApiAuthHeader(): string
{
    return 'Bearer test-api-key';
}

it('creates domain via POST /v1/domains with api key header', function (): void {
    Http::fake([
        'https://mail-api.test/v1/domains' => Http::response(['domain' => 'acme.example.com'], 201),
    ]);

    $result = mailApiClient()->createDomain('acme.example.com');

    expect($result)->toMatchArray(['domain' => 'acme.example.com']);

    Http::assertSent(fn ($request) => $request->url() === 'https://mail-api.test/v1/domains'
        && $request->method() === 'POST'
        && $request['domain'] === 'acme.example.com'
        && $request->hasHeader('Authorization', mailApiAuthHeader()));
});

it('creates admin mailbox via POST /v1/mailboxes', function (): void {
    Http::fake([
        'https://mail-api.test/v1/mailboxes' => Http::response([
            'address' => 'admin@acme.example.com',
        ], 201),
    ]);

    $result = mailApiClient()->createMailbox(
        domain: 'acme.example.com',
        localPart: 'admin',
        password: 'Secret123!',
    );

    expect($result['address'])->toBe('admin@acme.example.com');

    Http::assertSent(fn ($request) => $request->url() === 'https://mail-api.test/v1/mailboxes'
        && $request->method() === 'POST'
        && $request['domain'] === 'acme.example.com'
        && $request['local_part'] === 'admin'
        && $request->hasHeader('Authorization', mailApiAuthHeader()));
});

it('throws MailApiException when domain creation fails', function (): void {
    Http::fake([
        'https://mail-api.test/v1/domains' => Http::response(['error' => 'domain_exists'], 409),
    ]);

    mailApiClient()->createDomain('taken.example.com');
})->throws(MailApiException::class);

it('throws MailApiException when mailbox creation fails', function (): void {
    Http::fake([
        'https://mail-api.test/v1/mailboxes' => Http::response(['error' => 'quota_exceeded'], 422),
    ]);

    mailApiClient()->createMailbox('acme.example.com', 'admin', 'Secret123!');
})->throws(MailApiException::class);

it('isHealthy returns true when mail API health endpoint responds 200', function (): void {
    Http::fake([
        'https://mail-api.test/v1/health' => Http::response(['status' => 'ok'], 200),
    ]);

    expect(mailApiClient()->isHealthy())->toBeTrue();
});

it('isHealthy returns false when mail API health endpoint is unreachable', function (): void {
    Http::fake([
        'https://mail-api.test/v1/health' => Http::response(['error' => 'down'], 503),
    ]);

    expect(mailApiClient()->isHealthy())->toBeFalse();
});
