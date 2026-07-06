<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain;

enum DomainError: string
{
    case ValidationFailed = 'validation_failed';
    case Unauthenticated = 'unauthenticated';
    case ForbiddenScope = 'forbidden_scope';
    case TenantNotFound = 'tenant_not_found';
    case TenantNotReady = 'tenant_not_ready';
    case IdempotencyConflict = 'idempotency_conflict';
    case StateConflict = 'state_conflict';
    case ClusterUnreachable = 'cluster_unreachable';
    case UpstreamUnavailable = 'upstream_unavailable';
    case CapabilityNotAvailable = 'capability_not_available';
    case RateLimited = 'rate_limited';
    case NotImplemented = 'not_implemented';
    case RouteNotFound = 'route_not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case AppCatalogNotFound = 'app_catalog_not_found';

    public function httpStatus(): int
    {
        return match ($this) {
            self::ValidationFailed => 422,
            self::Unauthenticated => 401,
            self::ForbiddenScope => 403,
            self::TenantNotFound, self::CapabilityNotAvailable, self::AppCatalogNotFound => 404,
            self::IdempotencyConflict, self::StateConflict => 409,
            self::RateLimited => 429,
            self::NotImplemented => 501,
            self::UpstreamUnavailable => 502,
            self::TenantNotReady, self::ClusterUnreachable => 503,
            self::RouteNotFound => 404,
            self::MethodNotAllowed => 405,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::ValidationFailed => 'The request payload failed validation.',
            self::Unauthenticated => 'Authentication is required.',
            self::ForbiddenScope => 'The credential does not have the required scope.',
            self::TenantNotFound => 'The requested tenant was not found.',
            self::TenantNotReady => 'The tenant is not ready for this operation.',
            self::IdempotencyConflict => 'An idempotency conflict occurred.',
            self::StateConflict => 'The request conflicts with the current state.',
            self::ClusterUnreachable => 'The cluster is unreachable.',
            self::UpstreamUnavailable => 'The upstream service is unavailable.',
            self::CapabilityNotAvailable => 'The requested capability is not available.',
            self::RateLimited => 'Too many requests.',
            self::NotImplemented => 'This capability is not implemented yet.',
            self::RouteNotFound => 'The requested route was not found.',
            self::MethodNotAllowed => 'The HTTP method is not allowed for this route.',
            self::AppCatalogNotFound => 'The requested app catalog entry was not found.',
        };
    }
}
