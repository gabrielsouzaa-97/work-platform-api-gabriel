<?php

declare(strict_types=1);

use App\Modules\Core\Translators\Exceptions\UnknownStateException;
use App\Modules\Core\Translators\StateTranslator;

beforeEach(function (): void {
    $this->translator = new StateTranslator;
});

it('translates pending to queued', function (): void {
    expect($this->translator->toCanonical('pending'))->toBe('queued');
});

it('translates done to success', function (): void {
    expect($this->translator->toCanonical('done'))->toBe('success');
});

it('translates aborted to cancelled', function (): void {
    expect($this->translator->toCanonical('aborted'))->toBe('cancelled');
});

it('throws UnknownStateException for unknown state', function (): void {
    expect(fn () => $this->translator->toCanonical('UNKNOWN'))
        ->toThrow(UnknownStateException::class, 'UNKNOWN');
});

it('is case insensitive for uppercase input', function (): void {
    expect($this->translator->toCanonical('DONE'))->toBe('success');
});

it('covers all 5 upstream states', function (string $upstream, string $canonical): void {
    expect($this->translator->toCanonical($upstream))->toBe($canonical);
})->with([
    ['pending', 'queued'],
    ['running', 'running'],
    ['done', 'success'],
    ['error', 'failed'],
    ['aborted', 'cancelled'],
]);
