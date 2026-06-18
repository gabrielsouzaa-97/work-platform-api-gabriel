<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Modules\Core\Domain\DomainError;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\TenantNotReadyException;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RenderDomainError
{
    public static function isV1(Request $request): bool
    {
        return $request->is('api/v1', 'api/v1/*');
    }

    public static function response(
        DomainError $error,
        ?string $message = null,
        ?int $retryAfter = null,
        ?array $details = null,
    ): JsonResponse {
        $payload = [
            'code' => $error->value,
            'message' => $message ?? $error->defaultMessage(),
        ];

        if ($retryAfter !== null) {
            $payload['retry_after'] = $retryAfter;
        }

        if ($details !== null) {
            $payload['details'] = $details;
        }

        $response = response()->json(['error' => $payload], $error->httpStatus());

        if ($retryAfter !== null) {
            $response->header('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    public static function respond(
        Request $request,
        DomainError $error,
        ?string $message = null,
        ?int $retryAfter = null,
        ?array $details = null,
    ): JsonResponse {
        if (self::isV1($request)) {
            return self::response($error, $message, $retryAfter, $details);
        }

        return self::legacyFlatResponse($error, $retryAfter);
    }

    public static function renderNotFound(NotFoundHttpException $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api', 'api/*')) {
            return null;
        }

        if (self::isV1($request)) {
            return self::resolveV1NotFound($e, $request);
        }

        return self::legacyApiNotFound($e, $request);
    }

    public static function renderMethodNotAllowed(
        MethodNotAllowedHttpException $e,
        Request $request,
    ): ?JsonResponse {
        if (! $request->is('api', 'api/*')) {
            return null;
        }

        if (self::isV1($request)) {
            return self::response(DomainError::MethodNotAllowed);
        }

        return response()->json([
            'error' => 'method_not_allowed',
            'path' => $request->getPathInfo(),
            'method' => $request->method(),
        ], 405);
    }

    public static function mapSshRemoteException(
        SshRemoteException $e,
        Request $request,
        array $legacyExtra = [],
    ): JsonResponse {
        if (! self::isV1($request)) {
            return self::legacySshRemoteResponse($e, $legacyExtra);
        }

        return match ($e->remoteExitCode) {
            4 => self::response(DomainError::StateConflict),
            16 => self::response(DomainError::CapabilityNotAvailable),
            22 => self::response(
                DomainError::ValidationFailed,
                'Password does not meet requirements.',
            ),
            default => self::response(DomainError::UpstreamUnavailable),
        };
    }

    private static function resolveV1NotFound(NotFoundHttpException $e, Request $request): JsonResponse
    {
        if ($e->getPrevious() instanceof ModelNotFoundException && self::isTenantPath($request)) {
            return self::response(DomainError::TenantNotFound);
        }

        return self::response(DomainError::RouteNotFound);
    }

    private static function isTenantPath(Request $request): bool
    {
        return (bool) preg_match('#^/api/v1/tenants/[^/]+$#', $request->getPathInfo());
    }

    private static function legacyApiNotFound(NotFoundHttpException $e, Request $request): JsonResponse
    {
        $error = $e->getPrevious() instanceof ModelNotFoundException
            ? 'not_found'
            : 'route_not_found';

        return response()->json([
            'error' => $error,
            'path' => $request->getPathInfo(),
            'method' => $request->method(),
        ], 404);
    }

    private static function legacyFlatResponse(DomainError $error, ?int $retryAfter): JsonResponse
    {
        $response = response()->json(['error' => $error->value], $error->httpStatus());

        if ($retryAfter !== null) {
            $response->header('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    private static function legacySshRemoteResponse(SshRemoteException $e, array $legacyExtra = []): JsonResponse
    {
        if ($e->remoteExitCode === 4) {
            return response()->json(['error' => 'already_exists'], 409);
        }

        if ($e->remoteExitCode === 22) {
            return response()->json([
                'error' => 'validation_failed',
                'message' => 'Password does not meet requirements.',
            ], 422);
        }

        return response()->json(array_merge([
            'error' => 'upstream_error',
            'exit_code' => $e->remoteExitCode,
        ], $legacyExtra), 502);
    }

    public static function tenantNotReadyResponse(Request $request, TenantNotReadyException $e): JsonResponse
    {
        if (self::isV1($request)) {
            return self::response(
                DomainError::TenantNotReady,
                retryAfter: $e->retryAfterSeconds,
            );
        }

        return response()->json([
            'error' => 'tenant_not_ready',
            'status' => $e->customerStatus,
        ], 503)->header('Retry-After', (string) $e->retryAfterSeconds);
    }

    public static function clusterUnreachableResponse(Request $request): JsonResponse
    {
        if (self::isV1($request)) {
            return self::response(DomainError::ClusterUnreachable, retryAfter: 60);
        }

        return response()->json(['error' => 'cluster_unreachable'], 503)
            ->header('Retry-After', '60');
    }

    public static function idempotencyConflictResponse(
        Request $request,
        IdempotencyConflictException $e,
    ): JsonResponse {
        if (self::isV1($request)) {
            return self::response(
                DomainError::IdempotencyConflict,
                details: ['existing_job_id' => $e->getExistingJobId()],
            );
        }

        return response()->json([
            'error' => 'idempotency_conflict',
            'existing_job_id' => $e->getExistingJobId(),
        ], 409);
    }

    public static function unwrapTransport(\Throwable $e): \Throwable
    {
        if ($e instanceof UpstreamUnavailableException) {
            return $e->transportCause() ?? $e;
        }

        return $e;
    }

    /**
     * Maps PlatformPort transport/domain failures to JSON. Returns null if unhandled.
     *
     * @param  array<string, mixed>  $legacyExtra
     */
    public static function mapPortTransportException(
        \Throwable $e,
        Request $request,
        array $legacyExtra = [],
        ?string $timeoutError = null,
    ): ?JsonResponse {
        if ($e instanceof CapabilityBlockedException) {
            if (self::isV1($request)) {
                return self::response(DomainError::CapabilityNotAvailable);
            }

            return response()->json(['error' => 'capability_blocked'], 403);
        }

        $root = self::unwrapTransport($e);

        if ($root instanceof SshTimeoutException && $timeoutError !== null) {
            return response()->json(['error' => $timeoutError], 504);
        }

        if ($root instanceof SshRemoteException) {
            return self::mapSshRemoteException($root, $request, $legacyExtra);
        }

        if ($e instanceof UpstreamUnavailableException) {
            if (self::isV1($request)) {
                return self::response(DomainError::UpstreamUnavailable);
            }

            return response()->json(array_merge([
                'error' => 'upstream_error',
                'message' => $e->getMessage(),
            ], $legacyExtra), 502);
        }

        return null;
    }
}
