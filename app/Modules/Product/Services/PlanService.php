<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

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

            return Plan::create($this->planAttributes($data, $isDefault));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $slug, array $data): Plan
    {
        return DB::transaction(function () use ($slug, $data): Plan {
            $plan = Plan::query()->findOrFail($slug);
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

            return $plan->refresh();
        });
    }

    public function setAsDefault(string $slug): void
    {
        DB::transaction(function () use ($slug): void {
            Plan::query()->findOrFail($slug);
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

        $query->update(['is_default' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function planAttributes(array $data, bool $isDefault, ?Plan $existing = null): array
    {
        $attributes = [];

        foreach (['slug', 'name', 'description', 'default_quota', 'max_users', 'max_apps', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if ($existing === null || array_key_exists('is_default', $data)) {
            $attributes['is_default'] = $isDefault;
        }

        return $attributes;
    }
}
