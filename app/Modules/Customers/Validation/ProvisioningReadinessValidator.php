<?php

declare(strict_types=1);

namespace App\Modules\Customers\Validation;

use App\Models\ClusterServer;
use App\Modules\Customers\Contracts\ProvisioningReadinessContract;
use App\Modules\Customers\Dto\ResolvedProvisionContext;
use App\Modules\Integration\Services\SuiteCatalogAppLister;
use Illuminate\Validation\ValidationException;

final class ProvisioningReadinessValidator
{
    public function __construct(
        private readonly ProvisioningReadinessContract $contract,
        private readonly SuiteCatalogAppLister $catalogAppLister,
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
            if ($this->isLegacySuiteCatalogSatisfiedByClusterCatalog($context)) {
                return null;
            }

            return $this->unsatisfiableViolation($this->contract->legacyRequiredAppIds());
        }

        if (! $this->requiresLegacySuiteCheck($context)) {
            return null;
        }

        $missing = $this->missingLegacyApps($context);

        if ($missing === [] || $this->isLegacySuiteCatalogSatisfiedByClusterCatalog($context)) {
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

    private function isLegacySuiteCatalogSatisfiedByClusterCatalog(
        ResolvedProvisionContext $context,
    ): bool {
        if (! $this->requiresLegacySuiteCheck($context)) {
            return false;
        }

        if ($context->clusterServerId === null) {
            return false;
        }

        $cluster = ClusterServer::find($context->clusterServerId);
        if ($cluster === null || ! $cluster->legacy_me360_capable) {
            return false;
        }

        return $this->catalogContainsLegacyRequiredApps();
    }

    private function catalogContainsLegacyRequiredApps(): bool
    {
        $activeApps = array_flip($this->catalogAppLister->activeAppIds());

        foreach ($this->contract->legacyRequiredAppIds() as $appId) {
            if (! isset($activeApps[$appId])) {
                return false;
            }
        }

        return true;
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
