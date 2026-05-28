<?php

declare(strict_types=1);

namespace App\Modules\Core\Translators;

use App\Modules\Core\Translators\Exceptions\BlockedOnUpstreamException;
use App\Modules\Core\Translators\Exceptions\UnknownVerbException;

/**
 * Translates between the three vocabularies used in this orchestrator:
 *
 *   1. `cmd_canonical`  — internal API verb (e.g. `users:create`)
 *   2. `job_type`       — webhook/persistence enum (e.g. `user_create`)
 *   3. CLI argv upstream — tokens passed to `nextcloud-manage` (e.g. `['user', 'create']`)
 *
 * Mappings #1↔#2 are covered by cmdToJobType()/jobTypeToCmd().
 * Mapping #1→#3 is covered by cmdToCliArgv() (added in Sprint F5 / ISSUE-006).
 *
 * `groups:add` and `groups:remove` are intentionally absent from the argv map —
 * upstream `group modify` (v12.3.0) is a rename verb, not membership; the real
 * `group add-user`/`group remove-user` are pending in `mework360-deployer-scripts`
 * (D3/D4). cmdToCliArgv() throws BlockedOnUpstreamException for those so HTTP
 * 501 can be returned explicitly.
 */
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

    /**
     * Canonical cmd → upstream CLI argv tokens.
     *
     * Covers only the lifecycle verbs dispatched via LifecycleAsyncAction (users/groups/apps).
     * Customer-level verbs (create, remove, backup, restore, update, stop, start) are
     * intentionally absent — ProvisionCustomerAction and RemoveCustomerAction build their
     * own argv directly and do NOT route through cmdToCliArgv().
     *
     * Mapping confirmed via SSH probing against cluster `homolog` (upstream v12.3.0)
     * — `nextcloud-manage` uses the hierarchical namespace `user create|remove`,
     * `group create|remove`, `apps enable|disable`. The flat `user-create`/`user-delete`
     * forms listed in `SSH API Reference §14` are stale doc; §3.3 is the truth.
     *
     * @var array<string, list<string>>
     */
    private const CMD_TO_CLI_ARGV = [
        'users:create' => ['user', 'create'],
        'users:delete' => ['user', 'remove'],   // upstream verb is `remove`, NOT `delete`
        'groups:create' => ['group', 'create'],
        'groups:delete' => ['group', 'remove'], // upstream verb is `remove`, NOT `delete`
        // 'groups:add' and 'groups:remove' INTENTIONALLY absent — see BLOCKED_ON_UPSTREAM.
        'apps:enable' => ['apps', 'enable'],
        'apps:disable' => ['apps', 'disable'],
    ];

    /** @var array<string, string> */
    private const BLOCKED_ON_UPSTREAM = [
        'groups:add' => 'group membership add not implemented upstream (mework360-deployer-scripts D3/D4 pending)',
        'groups:remove' => 'group membership remove not implemented upstream (mework360-deployer-scripts D3/D4 pending)',
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

    /**
     * @return list<string>
     *
     * @throws BlockedOnUpstreamException when the verb exists in the canonical
     *                                    vocabulary but the upstream implementation is pending (groups:add/remove).
     * @throws UnknownVerbException when $cmd is not registered in any mapping.
     */
    public function cmdToCliArgv(string $cmd): array
    {
        if ($cmd === '') {
            throw new UnknownVerbException('Command cannot be empty');
        }

        if (isset(self::BLOCKED_ON_UPSTREAM[$cmd])) {
            throw new BlockedOnUpstreamException(self::BLOCKED_ON_UPSTREAM[$cmd], cmd: $cmd);
        }

        return self::CMD_TO_CLI_ARGV[$cmd]
            ?? throw new UnknownVerbException(
                "Unknown cmd: '{$cmd}'. Update CMD_TO_CLI_ARGV mapping to register new verbs."
            );
    }
}
