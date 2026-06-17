<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\V1\TenantResource;
use App\Models\Customer;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait RespondsWithV1Envelope
{
    protected function v1JobStatusUrl(string $jobId): string
    {
        return '/v1/jobs/'.$jobId;
    }

    protected function v1SyncEnvelope(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->resolveV1Data($data)], $status);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function v1AsyncEnvelope(string $jobId, array $data = []): JsonResponse
    {
        return response()->json([
            'data' => $data === [] ? (object) [] : $data,
            'meta' => [
                'job_id' => $jobId,
                'status_url' => $this->v1JobStatusUrl($jobId),
            ],
        ], 202);
    }

    protected function wrapV1JsonResponse(JsonResponse $response): JsonResponse
    {
        $status = $response->getStatusCode();

        if ($status === 202) {
            $body = $response->getData(true);

            if (is_array($body) && isset($body['job_id'])) {
                return $this->wrapAsyncJobIdBody($body);
            }
        }

        return $response;
    }

    protected function wrapV1ProvisionResource(CustomerResource $resource): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $resource->resource;

        $job = Job::query()
            ->where('customer_slug', $customer->slug)
            ->orderByDesc('queued_at')
            ->first();

        $tenantData = (new TenantResource($customer))->resolve(request());

        if ($job === null) {
            return $this->v1SyncEnvelope($tenantData);
        }

        return $this->v1AsyncEnvelope($job->job_id, $tenantData);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function wrapAsyncJobIdBody(array $body): JsonResponse
    {
        $jobId = (string) $body['job_id'];
        unset($body['job_id']);

        if (isset($body['apps_csv'])) {
            $apps = array_values(array_filter(
                explode(',', (string) $body['apps_csv']),
                static fn (string $app): bool => $app !== '',
            ));
            unset($body['apps_csv']);

            return $this->v1AsyncEnvelope($jobId, ['apps' => $apps]);
        }

        return $this->v1AsyncEnvelope($jobId, $body);
    }

    private function resolveV1Data(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve(request());
        }

        return $data;
    }
}
