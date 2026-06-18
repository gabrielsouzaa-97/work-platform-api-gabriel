<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithV1Envelope;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\RenderDomainError;
use App\Http\Requests\V1\CreateOnboardingRequest;
use App\Http\Resources\V1\OnboardingResource;
use App\Models\Onboarding;
use App\Models\Operator;
use App\Modules\Core\Domain\DomainError;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\StateConflictException;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Onboarding\Dto\OnboardingSpec;
use App\Modules\Onboarding\Saga\OnboardingSaga;
use App\Modules\Onboarding\Support\OnboardingIdempotencyKey;
use Illuminate\Http\JsonResponse;

final class OnboardingV1Controller extends Controller
{
    use RespondsWithV1Envelope;

    public function __construct(private readonly OnboardingSaga $saga) {}

    public function store(CreateOnboardingRequest $request): JsonResponse
    {
        $slug = $request->string('tenant.slug')->toString();

        if ($denied = $this->forbiddenForTenant($slug)) {
            return $denied;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);

        $existing = OnboardingIdempotencyKey::findRecentReplay($idempotencyKey);

        if ($existing !== null) {
            return $this->onboardingAcceptedResponse(new OnboardingResource($existing));
        }

        /** @var Operator $operator */
        $operator = $request->user();
        $apiKey = current_api_key();

        try {
            $onboarding = $this->saga->start(
                $this->toOnboardingSpec($request),
                $operator,
                $idempotencyKey,
                $apiKey?->id,
            );
        } catch (IdempotencyConflictException $e) {
            return RenderDomainError::idempotencyConflictResponse($request, $e);
        } catch (StateConflictException) {
            return RenderDomainError::respond($request, DomainError::StateConflict);
        } catch (ClusterUnreachableException) {
            return RenderDomainError::clusterUnreachableResponse($request);
        } catch (UpstreamUnavailableException|CapabilityBlockedException $e) {
            if ($response = RenderDomainError::mapPortTransportException($e, $request)) {
                return $response;
            }

            throw $e;
        }

        return $this->onboardingAcceptedResponse(new OnboardingResource($onboarding));
    }

    public function show(string $id): JsonResponse
    {
        $onboarding = Onboarding::query()->findOrFail($id);

        if ($denied = $this->forbiddenForTenant($onboarding->tenant_slug)) {
            return $denied;
        }

        return $this->v1SyncEnvelope(new OnboardingResource($onboarding));
    }

    private function toOnboardingSpec(CreateOnboardingRequest $request): OnboardingSpec
    {
        return new OnboardingSpec(
            tenantSlug: $request->string('tenant.slug')->toString(),
            domain: $request->string('tenant.domain')->toString(),
            clusterServerId: $request->string('tenant.cluster_server_id')->toString(),
            apps: $request->input('apps_enabled', []),
            fullApps: false,
            adminEmail: $request->string('admin.email')->toString(),
            adminDisplayName: $request->string('admin.username')->toString(),
        );
    }

    private function resolveIdempotencyKey(CreateOnboardingRequest $request): string
    {
        return OnboardingIdempotencyKey::hash([
            'slug' => $request->string('tenant.slug')->toString(),
            'domain' => $request->string('tenant.domain')->toString(),
            'admin_username' => $request->string('admin.username')->toString(),
        ]);
    }

    private function onboardingAcceptedResponse(OnboardingResource $resource): JsonResponse
    {
        $data = $resource->resolve(request());
        $onboardingId = (string) $data['id'];

        return response()->json([
            'data' => $data,
            'meta' => [
                'status_url' => $this->v1OnboardingStatusUrl($onboardingId),
            ],
        ], 202);
    }

    private function v1OnboardingStatusUrl(string $onboardingId): string
    {
        return '/v1/onboarding/'.$onboardingId;
    }

    private function forbiddenForTenant(string $slug): ?JsonResponse
    {
        $apiKey = current_api_key();

        if ($apiKey === null || $apiKey->allowed_tenant_slugs === null) {
            return null;
        }

        if (! in_array($slug, $apiKey->allowed_tenant_slugs, true)) {
            return RenderDomainError::response(DomainError::ForbiddenScope);
        }

        return null;
    }
}
