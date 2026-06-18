<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Jobs\Actions\CancelJobAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class JobController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'state' => ['nullable', 'string', 'in:queued,running,success,failed,cancelled'],
            'job_type' => ['nullable', 'string', 'max:100'],
            'customer' => ['nullable', 'string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = min($request->integer('per_page', 25), 100);

        $jobs = Job::query()
            ->with(['customer', 'clusterServer'])
            ->when($request->state, fn ($q, $s) => $q->where('state', $s))
            ->when($request->job_type, fn ($q, $t) => $q->where('job_type', $t))
            ->when($request->customer, fn ($q, $c) => $q->where('customer_slug', 'like', '%'.addcslashes($c, '%_').'%'))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return JobResource::collection($jobs);
    }

    public function show(string $id): JobResource
    {
        $job = Job::with(['customer', 'clusterServer'])->findOrFail($id);

        return new JobResource($job);
    }

    public function cancel(string $id, CancelJobAction $action): Response|JsonResponse
    {
        $job = Job::with('clusterServer')->findOrFail($id);

        try {
            $action->execute($job, auth()->id());
        } catch (\DomainException $e) {
            return response()->json(['error' => 'invalid_state', 'message' => $e->getMessage()], 422);
        } catch (UpstreamUnavailableException $e) {
            if ($response = RenderDomainError::mapPortTransportException($e, request())) {
                return $response;
            }

            return response()->json(['error' => 'upstream_error', 'message' => $e->getMessage()], 502);
        }

        return response()->noContent();
    }

    public function stats(): JsonResponse
    {
        $counts = Job::query()
            ->selectRaw('state, count(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state');

        return response()->json([
            'queued' => (int) ($counts['queued'] ?? 0),
            'running' => (int) ($counts['running'] ?? 0),
            'success' => (int) ($counts['success'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ]);
    }
}
