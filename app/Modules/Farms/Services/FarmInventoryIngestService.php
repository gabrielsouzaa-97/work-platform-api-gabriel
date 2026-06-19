<?php

declare(strict_types=1);

namespace App\Modules\Farms\Services;

use App\Models\FarmAgent;
use App\Models\FarmInventory;
use Illuminate\Support\Carbon;

final class FarmInventoryIngestService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(FarmAgent $agent, array $payload): FarmInventory
    {
        $capacity = $payload['capacity'];
        $maxTenants = (int) $capacity['max_tenants'];
        $activeTenants = (int) ($capacity['active_tenants'] ?? 0);
        $availableSlots = (int) ($capacity['available_slots'] ?? ($maxTenants - $activeTenants));

        return FarmInventory::updateOrCreate(
            ['farm_id' => $agent->farm_id],
            [
                'active_tenants' => $activeTenants,
                'max_tenants' => $maxTenants,
                'available_slots' => $availableSlots,
                'platform_version' => (string) ($payload['versions']['platform'] ?? ''),
                'latency_ms' => (int) ($payload['latency_ms'] ?? 0),
                'reported_at' => $this->parseReportedAt($payload['reported_at'] ?? null),
            ],
        );
    }

    private function parseReportedAt(mixed $value): Carbon
    {
        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return now();
    }
}
