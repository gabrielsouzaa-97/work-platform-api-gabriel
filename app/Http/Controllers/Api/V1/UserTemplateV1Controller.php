<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\V1\StoreUserTemplateRequest;
use App\Http\Requests\V1\UpdateUserTemplateRequest;
use App\Http\Resources\V1\UserTemplateResource;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Product\Services\UserTemplateService;
use Illuminate\Http\JsonResponse;

final class UserTemplateV1Controller extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly UserTemplateService $userTemplateService) {}

    public function index(): JsonResponse
    {
        $templates = $this->userTemplateService->list();

        return $this->v1SyncEnvelope(UserTemplateResource::collection($templates));
    }

    public function show(string $slug): JsonResponse
    {
        $template = $this->userTemplateService->findBySlug($slug);

        if ($template === null) {
            return RenderDomainError::response(DomainError::UserTemplateNotFound);
        }

        return $this->v1SyncEnvelope(new UserTemplateResource($template));
    }

    public function store(StoreUserTemplateRequest $request): JsonResponse
    {
        $template = $this->userTemplateService->create($request->validated());

        return $this->v1SyncEnvelope(new UserTemplateResource($template), 201);
    }

    public function update(string $slug, UpdateUserTemplateRequest $request): JsonResponse
    {
        $template = $this->userTemplateService->findBySlug($slug);

        if ($template === null) {
            return RenderDomainError::response(DomainError::UserTemplateNotFound);
        }

        $updated = $this->userTemplateService->update($slug, $request->validated());

        return $this->v1SyncEnvelope(new UserTemplateResource($updated));
    }
}
