<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

final class CustomerLifecycleStatus
{
    public const PROVISIONING = 'provisioning';

    public const PROVISIONING_FINISHING = 'provisioning_finishing';

    public const ACTIVE = 'active';

    /** @var list<string> */
    public const USER_OPS_BLOCKED = [
        self::PROVISIONING,
        self::PROVISIONING_FINISHING,
    ];
}
