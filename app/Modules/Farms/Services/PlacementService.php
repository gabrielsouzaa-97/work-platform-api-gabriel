<?php

declare(strict_types=1);

namespace App\Modules\Farms\Services;

use App\Models\FarmInventory;
use App\Modules\Farms\Dto\PlacementCriteria;
use App\Modules\Farms\Dto\PlacementResult;
use App\Modules\Farms\Exceptions\NoFarmCapacityException;
use Illuminate\Support\Collection;

final class PlacementService
{
    private const int FRESHNESS_WINDOW_HOURS = 1;

    public function select(PlacementCriteria $criteria): PlacementResult
    {
        $candidates = $this->loadCandidates();

        if ($candidates->isEmpty()) {
            throw new NoFarmCapacityException;
        }

        $best = $this->rankCandidates($candidates, $criteria)->first();

        if ($best === null) {
            throw new NoFarmCapacityException;
        }

        return new PlacementResult(
            farmId: $best->farm_id,
            clusterServerId: (string) $best->cluster_server_id,
            availableSlots: (int) $best->available_slots,
        );
    }

    /**
     * @return Collection<int, object{
     *     farm_id: string,
     *     available_slots: int,
     *     platform_version: string,
     *     latency_ms: int,
     *     cluster_server_id: string
     * }>
     */
    private function loadCandidates(): Collection
    {
        return FarmInventory::query()
            ->join('farm_agents', 'farm_inventories.farm_id', '=', 'farm_agents.farm_id')
            ->where('farm_agents.status', 'active')
            ->whereNull('farm_agents.deleted_at')
            ->where('farm_inventories.available_slots', '>', 0)
            ->where('farm_inventories.reported_at', '>=', now()->subHours(self::FRESHNESS_WINDOW_HOURS))
            ->whereNotNull('farm_agents.cluster_server_id')
            ->select([
                'farm_inventories.farm_id',
                'farm_inventories.available_slots',
                'farm_inventories.platform_version',
                'farm_inventories.latency_ms',
                'farm_agents.cluster_server_id',
            ])
            ->get();
    }

    /**
     * @param  Collection<int, object>  $candidates
     * @return Collection<int, object>
     */
    private function rankCandidates(Collection $candidates, PlacementCriteria $criteria): Collection
    {
        return $candidates->sort(function (object $left, object $right) use ($criteria): int {
            $slotCompare = $right->available_slots <=> $left->available_slots;
            if ($slotCompare !== 0) {
                return $slotCompare;
            }

            $versionCompare = $this->versionMatchScore($right, $criteria) <=> $this->versionMatchScore($left, $criteria);
            if ($versionCompare !== 0) {
                return $versionCompare;
            }

            return $left->latency_ms <=> $right->latency_ms;
        })->values();
    }

    private function versionMatchScore(object $candidate, PlacementCriteria $criteria): int
    {
        return $candidate->platform_version === $criteria->requiredPlatformVersion ? 1 : 0;
    }
}
