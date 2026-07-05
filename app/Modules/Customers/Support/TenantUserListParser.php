<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

use App\Models\TenantUser;

final class TenantUserListParser
{
    /**
     * @return list<array{username: string, email: ?string, quota: ?string, groups: list<string>}>
     */
    public static function parseUpstreamList(mixed $payload): array
    {
        $raw = self::extractRawRows($payload);
        $rows = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $parsed = self::parseUpstreamRow($item);
            if ($parsed['username'] !== '') {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    /**
     * @return array{username: string, email: string, quota: string, groups: string}
     */
    public static function toDisplayRow(TenantUser $user): array
    {
        $groups = $user->groups ?? [];
        $groupsStr = is_array($groups) ? implode(', ', $groups) : (string) $groups;

        return [
            'username' => $user->username,
            'email' => (string) ($user->email ?? ''),
            'quota' => (string) ($user->quota ?? '—'),
            'groups' => $groupsStr !== '' ? $groupsStr : '—',
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function extractRawRows(mixed $payload): array
    {
        return match (true) {
            is_array($payload) && isset($payload['users']) && is_array($payload['users']) => $payload['users'],
            is_array($payload) && array_is_list($payload) => $payload,
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{username: string, email: ?string, quota: ?string, groups: list<string>}
     */
    private static function parseUpstreamRow(array $item): array
    {
        $groups = $item['groups'] ?? [];
        $groupList = is_array($groups)
            ? array_values(array_filter(array_map('strval', $groups), static fn (string $g): bool => $g !== ''))
            : [];

        $quota = (string) ($item['quota'] ?? $item['file_quota'] ?? '');
        $email = (string) ($item['email'] ?? $item['mail'] ?? '');

        return [
            'username' => (string) ($item['username'] ?? $item['uid'] ?? $item['user_id'] ?? ''),
            'email' => $email !== '' ? $email : null,
            'quota' => $quota !== '' && $quota !== '—' ? $quota : null,
            'groups' => $groupList,
        ];
    }
}
