<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Models\AppCatalogEntry;
use App\Modules\Integration\Support\SuiteCatalogPathResolver;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class AppCatalogSyncService
{
    public function sync(?string $path = null): void
    {
        $catalogPath = SuiteCatalogPathResolver::resolve($path);

        $decoded = json_decode((string) File::get($catalogPath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Suite catalog JSON is invalid.');
        }

        /** @var array<int, array<string, mixed>> $apps */
        $apps = $decoded['apps'] ?? [];

        foreach ($apps as $entry) {
            $this->upsertEntry($entry);
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function upsertEntry(array $entry): void
    {
        $appId = $entry['app_id'] ?? null;

        if (! is_string($appId) || $appId === '') {
            return;
        }

        $status = is_string($entry['status'] ?? null) ? $entry['status'] : 'planned';
        $label = is_string($entry['label'] ?? null) && $entry['label'] !== ''
            ? $entry['label']
            : ucfirst($appId);

        AppCatalogEntry::query()->updateOrCreate(
            ['app_id' => $appId],
            [
                'label' => $label,
                'is_active' => $status === 'active',
            ],
        );
    }
}
