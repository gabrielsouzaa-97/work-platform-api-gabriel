<?php

declare(strict_types=1);

use App\Modules\Core\Translators\Exceptions\UnknownStateException;
use App\Modules\Core\Translators\StateTranslator;

beforeEach(function (): void {
    $this->translator = new StateTranslator;
});

it('translates queued to queued', function (): void {
    expect($this->translator->toCanonical('queued'))->toBe('queued');
});

it('translates done to success', function (): void {
    expect($this->translator->toCanonical('done'))->toBe('success');
});

it('translates cancelled to cancelled', function (): void {
    expect($this->translator->toCanonical('cancelled'))->toBe('cancelled');
});

it('throws UnknownStateException for unknown state', function (): void {
    expect(fn () => $this->translator->toCanonical('UNKNOWN'))
        ->toThrow(UnknownStateException::class, 'UNKNOWN');
});

it('throws UnknownStateException for legacy state names', function (string $legacy): void {
    expect(fn () => $this->translator->toCanonical($legacy))
        ->toThrow(UnknownStateException::class);
})->with(['pending', 'error', 'aborted']);

it('is case insensitive for uppercase input', function (): void {
    expect($this->translator->toCanonical('DONE'))->toBe('success');
});

it('covers all 5 upstream states', function (string $upstream, string $canonical): void {
    expect($this->translator->toCanonical($upstream))->toBe($canonical);
})->with([
    ['queued',    'queued'],
    ['running',   'running'],
    ['done',      'success'],
    ['failed',    'failed'],
    ['cancelled', 'cancelled'],
]);
