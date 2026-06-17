<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Resources\V1\JobResource;
use App\Models\Job;
use App\Modules\Core\Domain\DomainError;
use Illuminate\Http\JsonResponse;

final class JobV1Controller extends Controller
{
    use RespondsWithV1Envelope;

    public function show(string $id): JsonResponse
    {
        $job = Job::query()->findOrFail($id);

        if ($this->isJobForbiddenForCurrentApiKey($job)) {
            return RenderDomainError::response(DomainError::ForbiddenScope);
        }

        return $this->v1SyncEnvelope(new JobResource($job));
    }

    private function isJobForbiddenForCurrentApiKey(Job $job): bool
    {
        $apiKey = current_api_key();

        if ($apiKey === null || $apiKey->allowed_tenant_slugs === null) {
            return false;
        }

        return ! in_array($job->customer_slug, $apiKey->allowed_tenant_slugs, true);
    }
}
