<?php

declare(strict_types=1);

namespace App\Modules\Customers\Validation;

use App\Models\TenantGroup;
use Illuminate\Validation\ValidationException;

final class ResolvedTenantGroupsValidator
{
    /**
     * @param  list<string>|null  $groups
     * @param  list<string>  $subadminGroups
     *
     * @throws ValidationException
     */
    public static function validate(
        string $customerSlug,
        ?array $groups,
        array $subadminGroups = [],
    ): void {
        $errors = [];
        self::collectMissingGroupErrors($customerSlug, $groups, 'groups', $errors);
        self::collectMissingGroupErrors($customerSlug, $subadminGroups, 'subadmin_groups', $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<string>|null  $groups
     * @param  array<string, string>  $errors
     */
    private static function collectMissingGroupErrors(
        string $customerSlug,
        ?array $groups,
        string $attributePrefix,
        array &$errors,
    ): void {
        if ($groups === null || $groups === []) {
            return;
        }

        foreach ($groups as $index => $group) {
            if (! is_string($group) || $group === '' || strtolower($group) === 'admin') {
                continue;
            }

            $exists = TenantGroup::query()
                ->where('customer_slug', $customerSlug)
                ->whereRaw('LOWER(name) = ?', [strtolower($group)])
                ->exists();

            if (! $exists) {
                $errors["{$attributePrefix}.{$index}"] =
                    "Grupo '{$group}' não encontrado. Crie o grupo antes de atribuí-lo.";
            }
        }
    }
}
