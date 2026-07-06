<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
final class PlanResource extends JsonResource
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
            'max_users' => $this->max_users,
            'max_apps' => $this->max_apps,
            'is_default' => $this->is_default,
            'status' => $this->status,
        ];
    }
}
