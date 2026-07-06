<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\V1\StoreAppCatalogRequest;
use App\Http\Requests\V1\UpdateAppCatalogRequest;
use App\Http\Resources\V1\AppCatalogEntryResource;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Product\Services\AppCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppCatalogV1Controller extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly AppCatalogService $appCatalogService) {}

    public function index(Request $request): JsonResponse
    {
        $clusterServerId = $request->query('cluster_server_id');
        $clusterFilter = is_string($clusterServerId) && $clusterServerId !== ''
            ? $clusterServerId
            : null;

        $entries = $this->appCatalogService->list($clusterFilter);

        return $this->v1SyncEnvelope(AppCatalogEntryResource::collection($entries));
    }

    public function show(string $appId): JsonResponse
    {
        $entry = $this->appCatalogService->findByAppId($appId);

        if ($entry === null) {
            return RenderDomainError::response(DomainError::AppCatalogNotFound);
        }

        return $this->v1SyncEnvelope(new AppCatalogEntryResource($entry));
    }

    public function store(StoreAppCatalogRequest $request): JsonResponse
    {
        $entry = $this->appCatalogService->create($request->validated());

        return $this->v1SyncEnvelope(new AppCatalogEntryResource($entry), 201);
    }

    public function update(string $appId, UpdateAppCatalogRequest $request): JsonResponse
    {
        $entry = $this->appCatalogService->findByAppId($appId);

        if ($entry === null) {
            return RenderDomainError::response(DomainError::AppCatalogNotFound);
        }

        $updated = $this->appCatalogService->update($appId, $request->validated());

        return $this->v1SyncEnvelope(new AppCatalogEntryResource($updated));
    }
}
