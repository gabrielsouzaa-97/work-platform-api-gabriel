<?php

declare(strict_types=1);

namespace App\Modules\Core\Translators;

use App\Modules\Core\Translators\Exceptions\UnknownVerbException;

final class JobTypeTranslator
{
    private const CMD_TO_JOB_TYPE = [
        'create' => 'provision',
        'remove' => 'deprovision',
        'backup' => 'backup',
        'restore' => 'restore',
        'update' => 'update',
        'stop' => 'stop',
        'start' => 'start',
        'users:create' => 'user_create',
        'users:delete' => 'user_delete',
        'groups:create' => 'group_create',
        'groups:delete' => 'group_delete',
        'groups:add' => 'group_add_user',
        'groups:remove' => 'group_remove_user',
        'apps:enable' => 'apps_enable',
        'apps:disable' => 'apps_disable',
    ];

    /** @var array<string, string> */
    private const JOB_TYPE_TO_CMD = [
        'provision' => 'create',
        'deprovision' => 'remove',
        'backup' => 'backup',
        'restore' => 'restore',
        'update' => 'update',
        'stop' => 'stop',
        'start' => 'start',
        'user_create' => 'users:create',
        'user_delete' => 'users:delete',
        'group_create' => 'groups:create',
        'group_delete' => 'groups:delete',
        'group_add_user' => 'groups:add',
        'group_remove_user' => 'groups:remove',
        'apps_enable' => 'apps:enable',
        'apps_disable' => 'apps:disable',
    ];

    public function cmdToJobType(string $cmd): string
    {
        if ($cmd === '') {
            throw new UnknownVerbException('Command cannot be empty');
        }

        return self::CMD_TO_JOB_TYPE[$cmd]
            ?? throw new UnknownVerbException(
                "Unknown cmd: '{$cmd}'. Update CMD_TO_JOB_TYPE mapping to register new verbs."
            );
    }

    public function jobTypeToCmd(string $jobType): string
    {
        if ($jobType === '') {
            throw new UnknownVerbException('Job type cannot be empty');
        }

        return self::JOB_TYPE_TO_CMD[$jobType]
            ?? throw new UnknownVerbException(
                "Unknown job_type: '{$jobType}'. Update JOB_TYPE_TO_CMD mapping to register new types."
            );
    }
}
