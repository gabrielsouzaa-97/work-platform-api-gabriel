<?php

declare(strict_types=1);

use App\Modules\Jobs\Support\JobSummaryParser;

const EMBEDDED_FAILURE_JSON = '{"error":"occ_command_failed","subcommand":"group:adduser","stdout":"group not found"}';

beforeEach(function (): void {
    $this->parser = new JobSummaryParser;
});

it('embeddedFailure detects JSON occ_command_failed envelope in summary line', function (): void {
    $failure = $this->parser->embeddedFailure([EMBEDDED_FAILURE_JSON]);

    expect($failure)->toBe([
        'error' => 'occ_command_failed',
        'subcommand' => 'group:adduser',
        'stdout' => 'group not found',
    ]);
});

it('hasEmbeddedFailure returns true when summary contains occ_command_failed JSON', function (): void {
    expect($this->parser->hasEmbeddedFailure([
        '[INFO] User created',
        EMBEDDED_FAILURE_JSON,
    ]))->toBeTrue();
});

it('hasEmbeddedFailure returns false for clean success summary without JSON failure', function (): void {
    expect($this->parser->hasEmbeddedFailure([
        '[INFO] User johndoe created',
        '[INFO] Done',
    ]))->toBeFalse();
});

it('effectiveTerminalState maps success with embedded failure to partial', function (): void {
    expect($this->parser->effectiveTerminalState('success', [EMBEDDED_FAILURE_JSON]))
        ->toBe('partial');
});

it('effectiveTerminalState keeps success for clean summary', function (): void {
    expect($this->parser->effectiveTerminalState('success', ['[INFO] User created']))
        ->toBe('success');
});

it('effectiveTerminalState keeps failed unchanged even with embedded failure line', function (): void {
    expect($this->parser->effectiveTerminalState('failed', [EMBEDDED_FAILURE_JSON]))
        ->toBe('failed');
});

it('failureMessage returns readable message for embedded failure with subcommand', function (): void {
    $message = $this->parser->failureMessage([EMBEDDED_FAILURE_JSON]);

    expect($message)->toContain('group:adduser')
        ->and($message)->toContain('group not found');
});

it('failureMessage still detects legacy [ERROR] prefix regression', function (): void {
    $message = $this->parser->failureMessage([
        '[INFO] Starting user create',
        '[ERROR] admin already exists',
    ]);

    expect($message)->toBe('admin already exists');
});
