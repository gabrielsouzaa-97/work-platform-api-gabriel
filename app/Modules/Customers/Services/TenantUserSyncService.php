<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\TenantUser;
use App\Modules\Customers\Dto\TenantUserSyncReport;
use App\Modules\Customers\Support\TenantUserListParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TenantUserSyncService
{
    private const GRACE_MINUTES = 5;

    public function __construct(
        private readonly OccPassthroughService $occ,
    ) {}

    public function sync(Customer $customer): TenantUserSyncReport
    {
        $report = new TenantUserSyncReport;
        $upstreamRows = TenantUserListParser::parseUpstreamList(
            $this->occ->exec($customer, 'user:list', ['--json'], 30),
        );

        $localByUsername = TenantUser::query()
            ->where('customer_slug', $customer->slug)
            ->get()
            ->keyBy('username');

        $upstreamUsernames = $this->reconcileUpstream(
            $customer,
            $upstreamRows,
            $localByUsername,
            $report,
        );

        $this->deleteStaleRows(
            $localByUsername,
            $upstreamUsernames,
            Carbon::now()->subMinutes(self::GRACE_MINUTES),
            $report,
        );

        return $report;
    }

    /**
     * @param  list<array{username: string, email: ?string, quota: ?string, groups: list<string>}>  $upstreamRows
     * @param  Collection<string, TenantUser>  $localByUsername
     * @return list<string>
     */
    private function reconcileUpstream(
        Customer $customer,
        array $upstreamRows,
        Collection $localByUsername,
        TenantUserSyncReport $report,
    ): array {
        $upstreamUsernames = [];

        foreach ($upstreamRows as $row) {
            $username = $row['username'];
            $upstreamUsernames[] = $username;
            $local = $localByUsername->get($username);

            $this->detectAdminGroupDrift($customer, $username, $row['groups'], $local, $report);
            $this->upsertUpstreamRow($customer, $row, $local, $report);
        }

        return $upstreamUsernames;
    }

    /**
     * @param  array{username: string, email: ?string, quota: ?string, groups: list<string>}  $row
     */
    private function upsertUpstreamRow(
        Customer $customer,
        array $row,
        ?TenantUser $local,
        TenantUserSyncReport $report,
    ): void {
        if ($local === null) {
            $this->recordDrift($customer, $row['username'], 'manual_creation', $report);
            $this->insertFromSync($customer, $row);
            $report->inserted++;

            return;
        }

        if ($this->needsUpdate($local, $row)) {
            $local->update($this->syncAttributes($row));
            $report->updated++;
        }
    }

    /**
     * @param  list<string>  $groups
     */
    private function detectAdminGroupDrift(
        Customer $customer,
        string $username,
        array $groups,
        ?TenantUser $local,
        TenantUserSyncReport $report,
    ): void {
        if ($username === 'admin' && $local?->origin === 'provision') {
            return;
        }

        if ($username !== 'admin' && $this->hasAdminGroup($groups)) {
            $this->recordDrift($customer, $username, 'admin_group_member', $report);
        }
    }

    /**
     * @param  list<string>  $upstreamUsernames
     * @param  Collection<string, TenantUser>  $localByUsername
     */
    private function deleteStaleRows(
        Collection $localByUsername,
        array $upstreamUsernames,
        Carbon $graceCutoff,
        TenantUserSyncReport $report,
    ): void {
        foreach ($localByUsername as $username => $local) {
            if (in_array($username, $upstreamUsernames, true)) {
                continue;
            }

            if ($local->created_at !== null && $local->created_at->gte($graceCutoff)) {
                continue;
            }

            $local->delete();
            $report->deleted++;
        }
    }

    /**
     * @param  array{username: string, email: ?string, quota: ?string, groups: list<string>}  $row
     */
    private function insertFromSync(Customer $customer, array $row): void
    {
        TenantUser::create(array_merge(
            ['customer_slug' => $customer->slug, 'username' => $row['username'], 'origin' => 'sync'],
            $this->syncAttributes($row),
        ));
    }

    /**
     * @param  array{username: string, email: ?string, quota: ?string, groups: list<string>}  $row
     * @return array{email: ?string, quota: ?string, groups: list<string>, synced_at: Carbon}
     */
    private function syncAttributes(array $row): array
    {
        return [
            'email' => $row['email'],
            'quota' => $row['quota'],
            'groups' => $row['groups'],
            'synced_at' => Carbon::now(),
        ];
    }

    /**
     * @param  array{username: string, email: ?string, quota: ?string, groups: list<string>}  $row
     */
    private function needsUpdate(TenantUser $local, array $row): bool
    {
        return $local->email !== $row['email']
            || $local->quota !== $row['quota']
            || $this->normalizeGroups($local->groups) !== $this->normalizeGroups($row['groups']);
    }

    /**
     * @param  list<string>|null  $groups
     * @return list<string>
     */
    private function normalizeGroups(?array $groups): array
    {
        if ($groups === null) {
            return [];
        }

        $normalized = array_map(static fn (mixed $g): string => strtolower((string) $g), $groups);
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  list<string>  $groups
     */
    private function hasAdminGroup(array $groups): bool
    {
        foreach ($groups as $group) {
            if (strtolower((string) $group) === 'admin') {
                return true;
            }
        }

        return false;
    }

    private function recordDrift(
        Customer $customer,
        string $username,
        string $kind,
        TenantUserSyncReport $report,
    ): void {
        $report->driftDetected++;

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'tenant_user_drift',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'customer_slug' => $customer->slug,
                'username' => $username,
                'kind' => $kind,
            ],
            'cluster_server_id' => $customer->cluster_server_id,
        ]);
    }
}
