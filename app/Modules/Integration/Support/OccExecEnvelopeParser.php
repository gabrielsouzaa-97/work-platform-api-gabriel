<?php

declare(strict_types=1);

namespace App\Modules\Integration\Support;

final class OccExecEnvelopeParser
{
    /**
     * @param  array<string, mixed>|null  $parsedJson
     * @return array<string, mixed>|null
     */
    public static function unwrapPayload(?array $parsedJson): ?array
    {
        if ($parsedJson === null) {
            return null;
        }

        if (isset($parsedJson['parsed_result']) && is_array($parsedJson['parsed_result'])) {
            return $parsedJson['parsed_result'];
        }

        if (self::isDirectPayload($parsedJson)) {
            return $parsedJson;
        }

        if (isset($parsedJson['stdout']) && is_string($parsedJson['stdout'])) {
            $decoded = json_decode($parsedJson['stdout'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $enabled
     */
    public static function isAppEnabled(array $enabled, string $appId): bool
    {
        $map = self::resolveEnabledMap($enabled);
        if ($map === null || ! array_key_exists($appId, $map)) {
            return false;
        }

        $value = $map[$appId];

        return $value === true || (is_string($value) && $value !== '');
    }

    /**
     * @param  array<string, mixed>|null  $parsedJson
     */
    public static function configValue(?array $parsedJson, string $stdout = ''): ?string
    {
        if ($parsedJson !== null) {
            if (isset($parsedJson['parsed_result']) && is_string($parsedJson['parsed_result'])) {
                return self::nonEmptyString($parsedJson['parsed_result']);
            }

            $payload = self::unwrapPayload($parsedJson);
            if ($payload !== null && array_key_exists('value', $payload)) {
                $value = $payload['value'];

                return is_string($value) ? $value : null;
            }
        }

        $effectiveStdout = $stdout !== ''
            ? $stdout
            : (is_string($parsedJson['stdout'] ?? null) ? $parsedJson['stdout'] : '');

        return self::stdoutScalarValue(trim($effectiveStdout));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function resolveEnabledMap(array $data): ?array
    {
        if (isset($data['enabled']) && is_array($data['enabled'])) {
            return $data['enabled'];
        }

        $payload = self::unwrapPayload($data);
        if ($payload !== null && isset($payload['enabled']) && is_array($payload['enabled'])) {
            return $payload['enabled'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function isDirectPayload(array $data): bool
    {
        return array_key_exists('enabled', $data) || array_key_exists('value', $data);
    }

    private static function stdoutScalarValue(string $trimmed): ?string
    {
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_string($decoded)) {
                return self::nonEmptyString($decoded);
            }

            if (is_array($decoded)) {
                return null;
            }
        }

        return $trimmed;
    }

    private static function nonEmptyString(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }
}
