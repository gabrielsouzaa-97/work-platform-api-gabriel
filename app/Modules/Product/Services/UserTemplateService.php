<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Models\AppCatalogEntry;
use App\Models\UserTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class UserTemplateService
{
    /**
     * @return Collection<int, UserTemplate>
     */
    public function list(): Collection
    {
        return UserTemplate::query()
            ->with('appCatalogEntries')
            ->orderBy('name')
            ->get();
    }

    public function findBySlug(string $slug): ?UserTemplate
    {
        return UserTemplate::query()
            ->with('appCatalogEntries')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UserTemplate
    {
        return DB::transaction(function () use ($data): UserTemplate {
            $template = UserTemplate::create($this->templateAttributes($data));
            $this->syncAppIds($template, $data['app_ids'] ?? []);

            return $template->refresh()->load('appCatalogEntries');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $slug, array $data): UserTemplate
    {
        return DB::transaction(function () use ($slug, $data): UserTemplate {
            $template = UserTemplate::query()->where('slug', $slug)->firstOrFail();
            $template->fill($this->templateAttributes($data, $template));
            $template->save();

            if (array_key_exists('app_ids', $data)) {
                $this->syncAppIds($template, is_array($data['app_ids']) ? $data['app_ids'] : []);
            }

            return $template->refresh()->load('appCatalogEntries');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function templateAttributes(array $data, ?UserTemplate $existing = null): array
    {
        $attributes = [];

        foreach (['slug', 'name', 'description', 'default_quota', 'groups', 'permissions', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if ($existing === null && ! array_key_exists('status', $attributes)) {
            $attributes['status'] = 'active';
        }

        return $attributes;
    }

    /**
     * @param  list<string>  $appIds
     */
    private function syncAppIds(UserTemplate $template, array $appIds): void
    {
        if ($appIds === []) {
            $template->appCatalogEntries()->detach();

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

        $template->appCatalogEntries()->sync($syncPayload);
    }
}
