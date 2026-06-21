<?php

declare(strict_types=1);

use App\Modules\Billing\Exceptions\WhmcsApiException;
use App\Modules\Billing\Services\WhmcsClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'whmcs.url' => 'https://whmcs.test',
        'whmcs.identifier' => 'test-identifier',
        'whmcs.secret' => 'test-secret',
    ]);
});

function whmcsClient(): WhmcsClient
{
    return app(WhmcsClient::class);
}

it('addOrder posts AddOrder action to WHMCS API', function (): void {
    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::response([
            'result' => 'success',
            'orderid' => 42,
        ], 200),
    ]);

    $result = whmcsClient()->addOrder([
        'clientid' => 11,
        'pid' => [7],
        'paymentmethod' => 'vindi',
    ]);

    expect($result['orderid'])->toBe(42);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://whmcs.test/includes/api.php'
            && $request->method() === 'POST'
            && $request['action'] === 'AddOrder'
            && $request['identifier'] === 'test-identifier'
            && $request['secret'] === 'test-secret'
            && $request['clientid'] === 11
            && $request['pid'] === [7];
    });
});

it('acceptOrder posts AcceptOrder action to WHMCS API', function (): void {
    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::response([
            'result' => 'success',
            'orderid' => 42,
        ], 200),
    ]);

    $result = whmcsClient()->acceptOrder(42);

    expect($result['orderid'])->toBe(42);

    Http::assertSent(fn ($request) => $request['action'] === 'AcceptOrder'
        && $request['orderid'] === 42);
});

it('throws WhmcsApiException when WHMCS returns result error', function (): void {
    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::response([
            'result' => 'error',
            'message' => 'Invalid IP',
        ], 200),
    ]);

    whmcsClient()->addOrder(['clientid' => 1]);
})->throws(WhmcsApiException::class);

it('throws WhmcsApiException on HTTP failure', function (): void {
    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::response('upstream down', 502),
    ]);

    whmcsClient()->acceptOrder(1);
})->throws(WhmcsApiException::class);
