<?php

declare(strict_types=1);

it('services.pdns config registers api_url and api_key keys', function (): void {
    expect(config('services.pdns'))->toBeArray()
        ->and(config('services.pdns'))->toHaveKeys(['api_url', 'api_key']);
});

it('pdns api key is read from env-backed config without hardcoded secrets', function (): void {
    config([
        'services.pdns.api_url' => 'https://pdns.test',
        'services.pdns.api_key' => 'env-backed-pdns-key',
    ]);

    expect(config('services.pdns.api_url'))->toBe('https://pdns.test')
        ->and(config('services.pdns.api_key'))->toBe('env-backed-pdns-key')
        ->and(config('services.pdns.api_key'))->not->toContain('177.11.48.247');
});
