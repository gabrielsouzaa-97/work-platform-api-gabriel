<?php

declare(strict_types=1);

use App\Modules\Core\Translators\Exceptions\UnknownVerbException;
use App\Modules\Core\Translators\JobTypeTranslator;

beforeEach(function (): void {
    $this->translator = new JobTypeTranslator;
});

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
