<?php

declare(strict_types=1);

namespace App\Modules\Customers\Validation;

use App\Modules\Customers\Contracts\ProvisioningReadinessContract;
use App\Modules\Customers\Dto\ResolvedProvisionContext;
use Illuminate\Validation\ValidationException;

final class ProvisioningReadinessValidator
{
    public function __construct(
        private readonly ProvisioningReadinessContract $contract,
    ) {}

    /**
     * @throws ValidationException
     */
    public function assertValid(ResolvedProvisionContext $context): void
    {
        $violation = $this->validate($context);

        if ($violation !== null) {
            $violation->throw();
        }
    }

    public function validate(ResolvedProvisionContext $context): ?ProvisioningReadinessViolation
    {
        if ($this->contract->isSatisfiedByImageMode($context->imageMode)) {
            return null;
        }

        if ($context->resolvedApps === []) {
            return $this->unsatisfiableViolation($this->contract->legacyRequiredAppIds());
        }

        if (! $this->requiresLegacySuiteCheck($context)) {
            return null;
        }

        $missing = $this->missingLegacyApps($context);

        if ($missing === []) {
            return null;
        }

        return $this->unsatisfiableViolation($missing);
    }

    private function requiresLegacySuiteCheck(ResolvedProvisionContext $context): bool
    {
        if ($context->fullApps || $context->legacyVendor) {
            return false;
        }

        return $context->suiteCatalog;
    }

    /**
     * @return list<string>
     */
    private function missingLegacyApps(ResolvedProvisionContext $context): array
    {
        $resolved = array_flip($context->resolvedApps);
        $missing = [];

        foreach ($this->contract->legacyRequiredAppIds() as $appId) {
            if (! isset($resolved[$appId])) {
                $missing[] = $appId;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $missingPreconditions
     */
    private function unsatisfiableViolation(array $missingPreconditions): ProvisioningReadinessViolation
    {
        return new ProvisioningReadinessViolation($missingPreconditions);
    }
}
