<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\AppCatalogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AppCatalogEntry */
final class AppCatalogEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'app_id' => $this->app_id,
            'label' => $this->label,
            'description' => $this->description,
            'category' => $this->category,
            'cluster_server_id' => $this->cluster_server_id,
            'is_active' => $this->is_active,
        ];
    }
}
