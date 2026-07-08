<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\Job;
use App\Models\TenantGroup;

class TenantGroupProjector
{
    /** @var list<string> */
    private const GROUP_CREATE_TYPES = ['group_create', 'groups:create'];

    /** @var list<string> */
    private const GROUP_DELETE_TYPES = ['group_delete', 'groups:delete'];

    public function handleTerminalJob(Job $job, string $canonicalState): void
    {
        if ($canonicalState !== 'success' || $job->customer_slug === null) {
            return;
        }

        if (in_array($job->job_type, self::GROUP_CREATE_TYPES, true)) {
            $this->upsertGroupCreate($job);

            return;
        }

        if (in_array($job->job_type, self::GROUP_DELETE_TYPES, true)) {
            $this->deleteGroup($job);

            return;
        }

        if ($job->job_type === 'deprovision') {
            $this->purgeCustomer($job);
        }
    }

    private function upsertGroupCreate(Job $job): void
    {
        $name = $this->groupNameFromPayload($job);
        if ($name === null) {
            return;
        }

        $payload = $job->payload_sanitized ?? [];

        TenantGroup::updateOrCreate(
            [
                'customer_slug' => $job->customer_slug,
                'name' => $name,
            ],
            [
                'origin' => $this->resolveCreateOrigin($payload),
            ],
        );
    }

    private function deleteGroup(Job $job): void
    {
        $name = $this->groupNameFromPayload($job);
        if ($name === null) {
            return;
        }

        TenantGroup::query()
            ->where('customer_slug', $job->customer_slug)
            ->where('name', $name)
            ->delete();
    }

    private function purgeCustomer(Job $job): void
    {
        TenantGroup::query()
            ->where('customer_slug', $job->customer_slug)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCreateOrigin(array $payload): string
    {
        $origin = $payload['origin'] ?? 'api';

        return in_array($origin, ['api', 'panel'], true) ? $origin : 'api';
    }

    private function groupNameFromPayload(Job $job): ?string
    {
        $payload = $job->payload_sanitized ?? [];

        if (isset($payload['name'])) {
            $name = trim((string) $payload['name']);

            return $name !== '' ? $name : null;
        }

        $args = $payload['args'] ?? null;
        if (! is_array($args) || ! isset($args[0])) {
            return null;
        }

        $name = trim((string) $args[0]);

        return $name !== '' ? $name : null;
    }
}
