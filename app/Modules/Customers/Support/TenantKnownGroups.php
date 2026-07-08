<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

use App\Models\TenantGroup;

/**
 * Known group names for a tenant — backed by tenant_groups projection (N46).
 */
final class TenantKnownGroups
{
    /**
     * @return list<string>
     */
    public static function forCustomer(string $customerSlug): array
    {
        return TenantGroup::query()
            ->where('customer_slug', $customerSlug)
            ->whereRaw('LOWER(name) != ?', ['admin'])
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
