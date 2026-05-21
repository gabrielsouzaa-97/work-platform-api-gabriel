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

it('translates finished to success (worker.sh real implementation)', function (): void {
    // worker.sh emits "finished" on exit_code=0, even though nextcloud-manage §5.2
    // docstring says "done". Both are accepted to survive an upstream rename.
    expect($this->translator->toCanonical('finished'))->toBe('success');
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

it('translates success (callback wire value) to success', function (): void {
    // worker.sh emits state="success" on the HTTP callback payload (see worker.sh
    // comment near `final_state="success"`: "estado interno Redis = finished;
    // payload de callback = success"). Missing this caused 422 → http_code:0 retries.
    expect($this->translator->toCanonical('success'))->toBe('success');
});

it('translates canceled (US spelling, callback wire value) to cancelled', function (): void {
    // Worker comment references CONTRACTS.md §5.3 enum with "canceled" (one L).
    expect($this->translator->toCanonical('canceled'))->toBe('cancelled');
});

it('covers all upstream states (docstring + impl + wire)', function (string $upstream, string $canonical): void {
    expect($this->translator->toCanonical($upstream))->toBe($canonical);
})->with([
    ['queued',    'queued'],
    ['running',   'running'],
    ['done',      'success'],   // per nextcloud-manage §5.2 docstring
    ['finished',  'success'],   // per worker.sh internal Redis state
    ['success',   'success'],   // per worker.sh callback wire payload
    ['failed',    'failed'],
    ['cancelled', 'cancelled'],
    ['canceled',  'cancelled'], // US spelling on the wire
]);
