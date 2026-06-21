<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }
});

it('mail_api config file registers base_url and api_key keys', function (): void {
    expect(config('mail_api'))->toBeArray()
        ->and(config('mail_api'))->toHaveKeys(['base_url', 'api_key', 'api_key_encrypted']);
});

it('decrypts MAIL_API_KEY from encrypted config without exposing plaintext at rest', function (): void {
    $plainKey = 'mail-api-secret-key-'.uniqid();
    $encrypted = encrypt($plainKey);

    Config::set('mail_api.api_key_encrypted', $encrypted);

    expect(config('mail_api.api_key'))->toBe($plainKey)
        ->and(config('mail_api.api_key_encrypted'))->not->toBe($plainKey);
});

it('mail-api:health-check command succeeds when remote API is healthy', function (): void {
    Config::set('mail_api.base_url', 'https://mail-api.test');
    Config::set('mail_api.api_key', 'health-check-key');

    Http::fake([
        'https://mail-api.test/v1/health' => Http::response(['status' => 'ok'], 200),
    ]);

    Artisan::call('mail-api:health-check');

    expect(Artisan::output())->toContain('healthy');
});

it('mail-api:health-check command fails when remote API is down', function (): void {
    Config::set('mail_api.base_url', 'https://mail-api.test');
    Config::set('mail_api.api_key', 'health-check-key');

    Http::fake([
        'https://mail-api.test/v1/health' => Http::response(['error' => 'unavailable'], 503),
    ]);

    $this->artisan('mail-api:health-check')->assertFailed();
});
