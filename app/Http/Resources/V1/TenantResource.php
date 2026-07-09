<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'slug' => $this->slug,
            'cluster_server_id' => $this->cluster_server_id,
            'domain' => $this->domain,
            'status' => $this->status,
            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($this->status === CustomerLifecycleStatus::FAILED && $this->failure_reason !== null) {
            $data['failure_reason'] = $this->failure_reason;
        }

        return $data;
    }
}
