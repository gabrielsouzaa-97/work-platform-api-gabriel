<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Models\AppCatalogEntry;
use Illuminate\Database\Eloquent\Collection;

final class AppCatalogService
{
    /**
     * @return Collection<int, AppCatalogEntry>
     */
    public function list(?string $clusterServerId = null): Collection
    {
        $query = AppCatalogEntry::query()->orderBy('label');

        if ($clusterServerId !== null && $clusterServerId !== '') {
            $query->where(function ($builder) use ($clusterServerId): void {
                $builder->whereNull('cluster_server_id')
                    ->orWhere('cluster_server_id', $clusterServerId);
            });
        }

        return $query->get();
    }

    public function findByAppId(string $appId): ?AppCatalogEntry
    {
        return AppCatalogEntry::query()->where('app_id', $appId)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AppCatalogEntry
    {
        return AppCatalogEntry::create($this->entryAttributes($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $appId, array $data): AppCatalogEntry
    {
        $entry = AppCatalogEntry::query()->where('app_id', $appId)->firstOrFail();
        $entry->fill($this->entryAttributes($data, existing: $entry));
        $entry->save();

        return $entry->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function entryAttributes(array $data, ?AppCatalogEntry $existing = null): array
    {
        $attributes = [];

        foreach (['app_id', 'label', 'description', 'category', 'cluster_server_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if ($existing === null || array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) ($data['is_active'] ?? $existing?->is_active ?? true);
        }

        return $attributes;
    }
}
