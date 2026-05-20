<?php

declare(strict_types=1);

use App\Modules\Jobs\Dto\WebhookPayload;

it('factory mapeia payload completo de job.finished', function (): void {
    $payload = WebhookPayload::fromArray([
        'schema_version' => '1',
        'event' => 'job.finished',
        'job_id' => '550e8400-e29b-41d4-a716-446655440000',
        'state' => 'finished',
        'cmd' => 'provision',
        'client' => 'acme',
        'ts' => '2026-05-20T04:12:33Z',
        'started_at' => '2026-05-20T04:00:05Z',
        'finished_at' => '2026-05-20T04:12:33Z',
        'exit_code' => 0,
        'duration_ms' => 748000,
    ]);

    expect($payload->jobId)->toBe('550e8400-e29b-41d4-a716-446655440000')
        ->and($payload->event)->toBe('job.finished')
        ->and($payload->state)->toBe('finished')
        ->and($payload->cmd)->toBe('provision')
        ->and($payload->client)->toBe('acme')
        ->and($payload->startedAt)->toBe('2026-05-20T04:00:05Z')
        ->and($payload->finishedAt)->toBe('2026-05-20T04:12:33Z')
        ->and($payload->exitCode)->toBe(0)
        ->and($payload->durationMs)->toBe(748000)
        ->and($payload->isFinished())->toBeTrue()
        ->and($payload->isStarted())->toBeFalse();
});

it('factory mapeia payload de job.started sem finished_at/exit_code/duration_ms', function (): void {
    $payload = WebhookPayload::fromArray([
        'schema_version' => '1',
        'event' => 'job.started',
        'job_id' => '550e8400-e29b-41d4-a716-446655440000',
        'state' => 'running',
        'cmd' => 'provision',
        'client' => 'acme',
        'ts' => '2026-05-20T04:00:05Z',
        'started_at' => '2026-05-20T04:00:05Z',
    ]);

    expect($payload->event)->toBe('job.started')
        ->and($payload->state)->toBe('running')
        ->and($payload->startedAt)->toBe('2026-05-20T04:00:05Z')
        ->and($payload->finishedAt)->toBeNull()
        ->and($payload->exitCode)->toBeNull()
        ->and($payload->durationMs)->toBeNull()
        ->and($payload->isStarted())->toBeTrue()
        ->and($payload->isFinished())->toBeFalse();
});

it('payload legacy sem event é tratado como job.finished (backwards-compat)', function (): void {
    $payload = WebhookPayload::fromArray([
        'job_id' => 'aaaa',
        'state' => 'finished',
        'finished_at' => '2026-05-20T04:12:33Z',
        'exit_code' => 0,
    ]);

    expect($payload->event)->toBe(WebhookPayload::EVENT_FINISHED)
        ->and($payload->isFinished())->toBeTrue()
        ->and($payload->finishedAt)->toBe('2026-05-20T04:12:33Z');
});

it('factory usa ts como fallback para finished_at em job.finished', function (): void {
    $payload = WebhookPayload::fromArray([
        'event' => 'job.finished',
        'job_id' => 'bbbb',
        'state' => 'failed',
        'ts' => '2026-05-20T04:12:33Z',
        'exit_code' => 1,
    ]);

    expect($payload->finishedAt)->toBe('2026-05-20T04:12:33Z')
        ->and($payload->startedAt)->toBeNull();
});

it('factory usa ts como fallback para started_at em job.started', function (): void {
    $payload = WebhookPayload::fromArray([
        'event' => 'job.started',
        'job_id' => 'cccc',
        'state' => 'running',
        'ts' => '2026-05-20T04:00:05Z',
    ]);

    expect($payload->startedAt)->toBe('2026-05-20T04:00:05Z')
        ->and($payload->finishedAt)->toBeNull();
});

it('factory casteia exit_code e duration_ms para int', function (): void {
    $payload = WebhookPayload::fromArray([
        'event' => 'job.finished',
        'job_id' => 'dddd',
        'state' => 'finished',
        'finished_at' => '2026-05-20T04:12:33Z',
        'exit_code' => '0',
        'duration_ms' => '748000',
    ]);

    expect($payload->exitCode)->toBeInt()->toBe(0)
        ->and($payload->durationMs)->toBeInt()->toBe(748000);
});

it('factory aceita exit_code zero sem confundir com ausência', function (): void {
    $payload = WebhookPayload::fromArray([
        'event' => 'job.finished',
        'job_id' => 'eeee',
        'state' => 'finished',
        'finished_at' => '2026-05-20T04:12:33Z',
        'exit_code' => 0,
    ]);

    expect($payload->exitCode)->toBe(0)->not->toBeNull();
});

it('exporta constantes públicas EVENT_STARTED e EVENT_FINISHED', function (): void {
    expect(WebhookPayload::EVENT_STARTED)->toBe('job.started')
        ->and(WebhookPayload::EVENT_FINISHED)->toBe('job.finished');
});

it('cmd e client são opcionais e tornam-se null quando ausentes', function (): void {
    $payload = WebhookPayload::fromArray([
        'event' => 'job.finished',
        'job_id' => 'ffff',
        'state' => 'finished',
        'finished_at' => '2026-05-20T04:12:33Z',
        'exit_code' => 0,
    ]);

    expect($payload->cmd)->toBeNull()
        ->and($payload->client)->toBeNull();
});
