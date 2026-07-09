<?php

declare(strict_types=1);

namespace App\Modules\Integration\Services;

use App\Modules\Integration\Support\SuiteCatalogPathResolver;
use RuntimeException;

final class SuiteCatalogAppLister
{
    /**
     * @return list<string>
     */
    public function activeAppIds(): array
    {
        $path = SuiteCatalogPathResolver::resolve();
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Suite catalog JSON is invalid.');
        }

        /** @var array<int, array<string, mixed>> $entries */
        $entries = $decoded['apps'] ?? [];
        $active = [];

        foreach ($entries as $entry) {
            $appId = $entry['app_id'] ?? null;
            if (! is_string($appId) || $appId === '') {
                continue;
            }

            if (($entry['status'] ?? '') === 'active') {
                $active[] = $appId;
            }
        }

        return $active;
    }
}
