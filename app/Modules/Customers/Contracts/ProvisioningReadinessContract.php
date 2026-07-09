<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

final class ProvisioningReadinessContract
{
    /**
     * @return list<string>
     */
    public function legacyRequiredAppIds(): array
    {
        return ['mework360_memail', 'me360_theme'];
    }

    public function isSatisfiedByImageMode(bool $imageMode): bool
    {
        return $imageMode;
    }
}
