<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

use App\Models\TenantGroup;

final class TenantGroupListParser
{
    /**
     * @return list<string>
     */
    public static function parseUpstreamList(mixed $payload): array
    {
        $raw = self::extractRawRows($payload);
        $names = [];

        foreach ($raw as $item) {
            $name = self::parseUpstreamName($item);
            if ($name !== '' && strtolower($name) !== 'admin') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array{name: string, origin: string}
     */
    public static function toDisplayRow(TenantGroup $group): array
    {
        return [
            'name' => $group->name,
            'origin' => $group->origin,
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function extractRawRows(mixed $payload): array
    {
        return match (true) {
            is_array($payload) && isset($payload['groups']) && is_array($payload['groups']) => $payload['groups'],
            is_array($payload) && array_is_list($payload) => $payload,
            default => [],
        };
    }

    private static function parseUpstreamName(mixed $item): string
    {
        if (is_string($item)) {
            return trim($item);
        }

        if (! is_array($item)) {
            return '';
        }

        foreach (['name', 'gid', 'group', 'id'] as $key) {
            if (isset($item[$key]) && trim((string) $item[$key]) !== '') {
                return trim((string) $item[$key]);
            }
        }

        return '';
    }
}
