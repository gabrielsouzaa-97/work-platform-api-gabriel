<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\V1\StorePlanRequest;
use App\Http\Requests\V1\UpdatePlanRequest;
use App\Http\Resources\V1\PlanResource;
use App\Models\Plan;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Product\Services\PlanService;
use Illuminate\Http\JsonResponse;

final class PlanV1Controller extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly PlanService $planService) {}

    public function index(): JsonResponse
    {
        $plans = Plan::query()->orderBy('name')->get();

        return $this->v1SyncEnvelope(PlanResource::collection($plans));
    }

    public function show(string $slug): JsonResponse
    {
        $plan = Plan::query()->find($slug);

        if ($plan === null) {
            return RenderDomainError::response(DomainError::TenantNotFound);
        }

        return $this->v1SyncEnvelope(new PlanResource($plan));
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->planService->create($request->validated());

        return $this->v1SyncEnvelope(new PlanResource($plan), 201);
    }

    public function update(string $slug, UpdatePlanRequest $request): JsonResponse
    {
        $plan = Plan::query()->find($slug);

        if ($plan === null) {
            return RenderDomainError::response(DomainError::TenantNotFound);
        }

        $updated = $this->planService->update($slug, $request->validated());

        return $this->v1SyncEnvelope(new PlanResource($updated));
    }
}
