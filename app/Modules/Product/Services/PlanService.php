<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Models\AppCatalogEntry;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

final class PlanService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Plan
    {
        return DB::transaction(function () use ($data): Plan {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $this->clearDefaultFlag();
            }

            $plan = Plan::create($this->planAttributes($data, $isDefault));
            $this->syncAppIds($plan, $data['app_ids'] ?? []);

            return $plan->refresh()->load('appCatalogEntries');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $slug, array $data): Plan
    {
        return DB::transaction(function () use ($slug, $data): Plan {
            $plan = Plan::query()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $isDefault = array_key_exists('is_default', $data)
                ? (bool) $data['is_default']
                : $plan->is_default;

            if ($isDefault && ! $plan->is_default) {
                $this->clearDefaultFlag();
            } elseif ($isDefault) {
                $this->clearDefaultFlag(exceptSlug: $slug);
            }

            $plan->fill($this->planAttributes($data, $isDefault, existing: $plan));
            $plan->save();

            if (array_key_exists('app_ids', $data)) {
                $this->syncAppIds($plan, is_array($data['app_ids']) ? $data['app_ids'] : []);
            }

            return $plan->refresh()->load('appCatalogEntries');
        });
    }

    public function setAsDefault(string $slug): void
    {
        DB::transaction(function () use ($slug): void {
            Plan::query()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $this->clearDefaultFlag(exceptSlug: $slug);
            Plan::query()->where('slug', $slug)->update(['is_default' => true]);
        });
    }

    private function clearDefaultFlag(?string $exceptSlug = null): void
    {
        $query = Plan::query()->where('is_default', true);

        if ($exceptSlug !== null) {
            $query->where('slug', '!=', $exceptSlug);
        }

        $query->lockForUpdate()->update(['is_default' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function planAttributes(array $data, bool $isDefault, ?Plan $existing = null): array
    {
        $attributes = [];

        foreach (['slug', 'name', 'description', 'default_quota', 'max_users', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if ($existing === null || array_key_exists('is_default', $data)) {
            $attributes['is_default'] = $isDefault;
        }

        return $attributes;
    }

    /**
     * @param  list<string>  $appIds
     */
    private function syncAppIds(Plan $plan, array $appIds): void
    {
        if ($appIds === []) {
            $plan->appCatalogEntries()->detach();

            return;
        }

        $catalogIds = AppCatalogEntry::query()
            ->whereIn('app_id', $appIds)
            ->pluck('id', 'app_id');

        $syncPayload = [];
        foreach ($appIds as $appId) {
            $catalogId = $catalogIds->get($appId);
            if ($catalogId !== null) {
                $syncPayload[$catalogId] = [];
            }
        }

        $plan->appCatalogEntries()->sync($syncPayload);
    }
}
