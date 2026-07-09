<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\Job;
use App\Models\TenantUser;

class TenantUserProjector
{
    /** @var list<string> */
    private const USER_CREATE_TYPES = ['user_create', 'users:create'];

    /** @var list<string> */
    private const USER_DELETE_TYPES = ['user_delete', 'users:delete'];

    public function handleTerminalJob(Job $job, string $effectiveState): void
    {
        if (! in_array($effectiveState, ['success', 'partial'], true) || $job->customer_slug === null) {
            return;
        }

        if (in_array($job->job_type, self::USER_CREATE_TYPES, true)) {
            $this->upsertUserCreate($job, $effectiveState === 'partial');

            return;
        }

        if (in_array($job->job_type, self::USER_DELETE_TYPES, true)) {
            $this->deleteUser($job);

            return;
        }

        if ($job->job_type === 'provision') {
            $this->upsertProvisionAdmin($job);

            return;
        }

        if ($job->job_type === 'deprovision') {
            $this->purgeCustomer($job);
        }
    }

    private function upsertUserCreate(Job $job, bool $omitGroups = false): void
    {
        $username = $this->usernameFromPayload($job);
        if ($username === null) {
            return;
        }

        $payload = $job->payload_sanitized ?? [];

        TenantUser::updateOrCreate(
            [
                'customer_slug' => $job->customer_slug,
                'username' => $username,
            ],
            [
                'email' => $payload['email'] ?? null,
                'quota' => $payload['quota'] ?? null,
                'groups' => $omitGroups ? null : ($payload['groups'] ?? null),
                'origin' => $this->resolveCreateOrigin($payload),
                'user_template_slug' => $payload['user_template_slug'] ?? null,
            ],
        );
    }

    private function deleteUser(Job $job): void
    {
        $username = $this->usernameFromPayload($job);
        if ($username === null) {
            return;
        }

        TenantUser::query()
            ->where('customer_slug', $job->customer_slug)
            ->where('username', $username)
            ->delete();
    }

    private function upsertProvisionAdmin(Job $job): void
    {
        TenantUser::updateOrCreate(
            [
                'customer_slug' => $job->customer_slug,
                'username' => 'admin',
            ],
            [
                'origin' => 'provision',
                'groups' => ['admin'],
            ],
        );
    }

    private function purgeCustomer(Job $job): void
    {
        TenantUser::query()
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

    private function usernameFromPayload(Job $job): ?string
    {
        $args = $job->payload_sanitized['args'] ?? null;
        if (! is_array($args) || ! isset($args[0])) {
            return null;
        }

        $username = (string) $args[0];

        return $username !== '' ? $username : null;
    }
}
