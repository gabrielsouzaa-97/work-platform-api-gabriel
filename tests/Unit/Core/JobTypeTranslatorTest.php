<?php

declare(strict_types=1);

use App\Modules\Core\Translators\Exceptions\BlockedOnUpstreamException;
use App\Modules\Core\Translators\Exceptions\UnknownVerbException;
use App\Modules\Core\Translators\JobTypeTranslator;

beforeEach(function (): void {
    $this->translator = new JobTypeTranslator;
});

// ── cmd ↔ job_type ────────────────────────────────────────────────────────────

it('translates cmd to job_type for known verb', function (): void {
    expect($this->translator->cmdToJobType('create'))->toBe('provision');
});

it('translates job_type to cmd for known job type', function (): void {
    expect($this->translator->jobTypeToCmd('provision'))->toBe('create');
});

it('throws UnknownVerbException for unknown cmd', function (): void {
    expect(fn () => $this->translator->cmdToJobType('unknown'))
        ->toThrow(UnknownVerbException::class, 'unknown');
});

it('throws UnknownVerbException for unknown job_type', function (): void {
    expect(fn () => $this->translator->jobTypeToCmd('nonexistent'))
        ->toThrow(UnknownVerbException::class, 'nonexistent');
});

it('performs stable roundtrip for all 15 verbs', function (string $jobType): void {
    $cmd = $this->translator->jobTypeToCmd($jobType);
    expect($this->translator->cmdToJobType($cmd))->toBe($jobType);
})->with([
    'provision',
    'deprovision',
    'backup',
    'restore',
    'update',
    'stop',
    'start',
    'user_create',
    'user_delete',
    'group_create',
    'group_delete',
    'group_add_user',
    'group_remove_user',
    'apps_enable',
    'apps_disable',
]);

it('covers all 15 cmd verbs in forward direction', function (string $cmd, string $expectedJobType): void {
    expect($this->translator->cmdToJobType($cmd))->toBe($expectedJobType);
})->with([
    ['create', 'provision'],
    ['remove', 'deprovision'],
    ['backup', 'backup'],
    ['restore', 'restore'],
    ['update', 'update'],
    ['stop', 'stop'],
    ['start', 'start'],
    ['users:create', 'user_create'],
    ['users:delete', 'user_delete'],
    ['groups:create', 'group_create'],
    ['groups:delete', 'group_delete'],
    ['groups:add', 'group_add_user'],
    ['groups:remove', 'group_remove_user'],
    ['apps:enable', 'apps_enable'],
    ['apps:disable', 'apps_disable'],
]);

// ── cmd → CLI argv (Sprint F5 / ISSUE-006) ────────────────────────────────────

it('cmdToCliArgv maps all canonical verbs to upstream argv tokens', function (string $cmd, array $expectedArgv): void {
    expect($this->translator->cmdToCliArgv($cmd))->toBe($expectedArgv);
})->with([
    // Customer-level verbs (positional after slug)
    ['create', ['create']],
    ['remove', ['remove']],
    ['backup', ['backup']],
    ['restore', ['restore']],
    ['update', ['update']],
    ['stop', ['stop']],
    ['start', ['start']],

    // Hierarchical namespace verbs — note `remove` (NOT `delete`) for delete operations
    ['users:create', ['user', 'create']],
    ['users:delete', ['user', 'remove']],
    ['groups:create', ['group', 'create']],
    ['groups:delete', ['group', 'remove']],
    ['apps:enable', ['apps', 'enable']],
    ['apps:disable', ['apps', 'disable']],
]);

it('cmdToCliArgv throws BlockedOnUpstreamException for groups:add (group membership pending)', function (): void {
    expect(fn () => $this->translator->cmdToCliArgv('groups:add'))
        ->toThrow(BlockedOnUpstreamException::class, 'group membership add not implemented upstream');
});

it('cmdToCliArgv throws BlockedOnUpstreamException for groups:remove (group membership pending)', function (): void {
    expect(fn () => $this->translator->cmdToCliArgv('groups:remove'))
        ->toThrow(BlockedOnUpstreamException::class, 'group membership remove not implemented upstream');
});

it('BlockedOnUpstreamException exposes the offending cmd', function (string $blockedCmd): void {
    try {
        $this->translator->cmdToCliArgv($blockedCmd);
        $this->fail('Expected BlockedOnUpstreamException');
    } catch (BlockedOnUpstreamException $e) {
        expect($e->cmd)->toBe($blockedCmd);
    }
})->with(['groups:add', 'groups:remove']);

it('cmdToCliArgv throws UnknownVerbException for empty cmd', function (): void {
    expect(fn () => $this->translator->cmdToCliArgv(''))
        ->toThrow(UnknownVerbException::class, 'Command cannot be empty');
});

it('cmdToCliArgv throws UnknownVerbException for unmapped cmd', function (): void {
    expect(fn () => $this->translator->cmdToCliArgv('inexistente:x'))
        ->toThrow(UnknownVerbException::class, 'inexistente:x');
});
