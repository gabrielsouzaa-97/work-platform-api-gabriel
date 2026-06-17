<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\JobResource;
use App\Models\Job;
use Illuminate\Http\JsonResponse;

final class JobV1Controller extends Controller
{
    use RespondsWithV1Envelope;

    public function show(string $id): JsonResponse
    {
        $job = Job::with(['customer', 'clusterServer'])->findOrFail($id);

        return $this->v1SyncEnvelope(new JobResource($job));
    }
}
