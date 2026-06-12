<?php

declare(strict_types=1);

use App\Logging\SshSecretsMasker;
use Monolog\Level;
use Monolog\LogRecord;

it('masks idempotency-key and callback args in log message', function () {
    $masker = new SshSecretsMasker;
    $message = 'nextcloud-manage slug occ-exec --idempotency-key=abc-123 --callback=https://example.com/hook';

    $record = $masker(new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'sshclient',
        level: Level::Info,
        message: $message,
        context: [],
        extra: [],
    ));

    expect($record->message)
        ->toContain('--idempotency-key=***')
        ->toContain('--callback=***')
        ->not->toContain('abc-123')
        ->not->toContain('https://example.com/hook');
});

it('masks sensitive args when command key is present in log context', function () {
    $masker = new SshSecretsMasker;
    $command = 'manage.sh run --idempotency-key=secret-uuid --callback=https://api.test/webhook';

    $record = $masker(new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'sshclient',
        level: Level::Info,
        message: 'SSH command executed',
        context: ['command' => $command],
        extra: [],
    ));

    expect($record->context['command'])
        ->toBe('manage.sh run --idempotency-key=*** --callback=***');
});

it('still redacts PEM private keys in messages', function () {
    $masker = new SshSecretsMasker;
    $pem = "-----BEGIN RSA PRIVATE KEY-----\nSECRET\n-----END RSA PRIVATE KEY-----";

    $record = $masker(new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'sshclient',
        level: Level::Info,
        message: $pem,
        context: [],
        extra: [],
    ));

    expect($record->message)->toBe('[REDACTED]');
});
