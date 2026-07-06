<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\UserTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserTemplate */
final class UserTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'default_quota' => $this->default_quota,
            'groups' => $this->groups,
            'permissions' => $this->permissions,
            'app_ids' => $this->relationLoaded('appCatalogEntries')
                ? $this->appCatalogEntries->pluck('app_id')->values()->all()
                : [],
            'status' => $this->status,
        ];
    }
}
