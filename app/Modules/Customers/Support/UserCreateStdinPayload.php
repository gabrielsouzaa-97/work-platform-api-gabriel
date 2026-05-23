<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

/**
 * Builds the JSON stdin payload for upstream `user create --payload-stdin`.
 *
 * Upstream schema (confirmed via SSH probing 2026-05-23):
 * {password, display_name?, email?, quota?, groups?, subadmin_groups?}
 */
final class UserCreateStdinPayload
{
    /**
     * @param  list<string>  $groups
     * @param  list<string>  $subadminGroups
     * @return array<string, mixed>
     */
    public static function build(
        string $password,
        ?string $displayName = null,
        ?string $email = null,
        ?string $quota = null,
        array $groups = [],
        array $subadminGroups = [],
    ): array {
        $payload = ['password' => $password];

        if ($displayName !== null && $displayName !== '') {
            $payload['display_name'] = $displayName;
        }

        if ($email !== null && $email !== '') {
            $payload['email'] = $email;
        }

        if ($quota !== null && $quota !== '') {
            $payload['quota'] = self::normalizeQuota($quota);
        }

        $groups = array_values(array_filter(
            $groups,
            static fn (mixed $g): bool => is_string($g) && $g !== '',
        ));
        if ($groups !== []) {
            $payload['groups'] = $groups;
        }

        $subadminGroups = array_values(array_filter(
            $subadminGroups,
            static fn (mixed $g): bool => is_string($g) && $g !== '',
        ));
        if ($subadminGroups !== []) {
            $payload['subadmin_groups'] = $subadminGroups;
        }

        return $payload;
    }

    public static function normalizeQuota(string $quota): string
    {
        $quota = trim($quota);

        if (in_array(strtolower($quota), ['none', 'default', 'unlimited'], true)) {
            return $quota;
        }

        return preg_replace('/\s+/', '', $quota) ?? $quota;
    }
}
