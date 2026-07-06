<?php

declare(strict_types=1);

namespace App\Modules\Product\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PermissionsSchemaV1 implements ValidationRule
{
    /** @var list<string> */
    private const TOP_LEVEL_KEYS = ['schema_version', 'users', 'apps', 'audit'];

    /** @var array<string, list<string>> */
    private const SECTION_KEYS = [
        'users' => ['hire', 'block', 'activate'],
        'apps' => ['install_from_store', 'create_integration'],
        'audit' => ['read'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The permissions field must be a valid object.');

            return;
        }

        foreach (array_keys($value) as $key) {
            if (! in_array($key, self::TOP_LEVEL_KEYS, true)) {
                $fail('The permissions field contains unknown keys.');

                return;
            }
        }

        foreach (self::SECTION_KEYS as $section => $allowedKeys) {
            if (! isset($value[$section]) || ! is_array($value[$section])) {
                $fail("The permissions.{$section} field is required.");

                return;
            }

            foreach (array_keys($value[$section]) as $sectionKey) {
                if (! in_array($sectionKey, $allowedKeys, true)) {
                    $fail('The permissions field contains unknown keys.');

                    return;
                }
            }

            foreach ($allowedKeys as $allowedKey) {
                if (! array_key_exists($allowedKey, $value[$section])) {
                    $fail("The permissions.{$section}.{$allowedKey} field is required.");

                    return;
                }

                if (! is_bool($value[$section][$allowedKey])) {
                    $fail("The permissions.{$section}.{$allowedKey} field must be true or false.");

                    return;
                }
            }
        }
    }
}
