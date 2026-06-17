<?php

declare(strict_types=1);

namespace App\Modules\Integration\Contracts;

use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Integration\Dto\AsyncJobRef;
use App\Modules\Integration\Dto\BrandingResult;
use App\Modules\Integration\Dto\CreateTenantCommand;
use App\Modules\Integration\Dto\EnableAppsCommand;
use App\Modules\Integration\Dto\ProbeReadinessCommand;
use App\Modules\Integration\Dto\ReadinessReport;
use App\Modules\Integration\Dto\SetBrandingCommand;

interface PlatformPort
{
    /**
     * @throws ClusterUnreachableException
     * @throws SshRemoteException
     */
    public function createTenant(CreateTenantCommand $command): AsyncJobRef;

    /**
     * @throws ClusterUnreachableException
     * @throws SshRemoteException
     */
    public function enableApps(EnableAppsCommand $command): AsyncJobRef;

    /**
     * @throws ClusterUnreachableException
     * @throws SshTimeoutException
     * @throws SshRemoteException
     */
    public function setBranding(SetBrandingCommand $command): BrandingResult;

    public function probeReadiness(ProbeReadinessCommand $command): ReadinessReport;
}
