<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

final class CustomerLifecycleMatrix
{
    /** @var array<string, list<CustomerLifecycleAction>> */
    private const MATRIX = [
        CustomerLifecycleStatus::ACTIVE => [
            CustomerLifecycleAction::LifecycleAsync,
            CustomerLifecycleAction::OccPanel,
            CustomerLifecycleAction::Remove,
        ],
        CustomerLifecycleStatus::PROVISIONING => [
            CustomerLifecycleAction::Remove,
        ],
        CustomerLifecycleStatus::PROVISIONING_FINISHING => [
            CustomerLifecycleAction::Remove,
            CustomerLifecycleAction::PromoteManual,
        ],
        CustomerLifecycleStatus::FAILED => [
            CustomerLifecycleAction::Remove,
        ],
        CustomerLifecycleStatus::REMOVING => [],
        CustomerLifecycleStatus::REMOVED => [],
        CustomerLifecycleStatus::ERROR => [],
    ];

    public static function allows(string $status, CustomerLifecycleAction $action): bool
    {
        return in_array($action, self::MATRIX[$status] ?? [], true);
    }

    public static function isActive(string $status): bool
    {
        return $status === CustomerLifecycleStatus::ACTIVE;
    }
}
