<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'job_id' => $this->job_id,
            'customer_slug' => $this->customer_slug,
            'cluster_server_id' => $this->cluster_server_id,
            'job_type' => $this->job_type,
            'state' => $this->state,
            'summary' => $this->summary,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'callback_received_at' => $this->callback_received_at?->toIso8601String(),
        ];
    }
}
