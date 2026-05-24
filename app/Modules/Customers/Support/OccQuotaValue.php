<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

/**
 * Normalizes quota labels for OCC argv over SSH (ISSUE-017).
 *
 * Human-facing API/UI may use spaced labels (`5 GB`) from quota/options, but the
 * ncsaas-api ForceCommand hop word-splits spaced tokens even when quoted. Upstream
 * OCC accepts compact forms (`5GB`) — same rule as UserCreateStdinPayload.
 */
final class OccQuotaValue
{
    public static function forSshArgv(string $quota): string
    {
        return UserCreateStdinPayload::normalizeQuota($quota);
    }
}
