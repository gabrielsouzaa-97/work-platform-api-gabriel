<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds Deprecation / Sunset / Link headers on legacy API routes that have a v1 successor.
 */
final class LegacyApiDeprecation
{
    private const SUNSET = 'Sat, 31 Dec 2026 23:59:59 GMT';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $successor = $this->successorPath($request);
        if ($successor === null) {
            return $response;
        }

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', self::SUNSET);
        $response->headers->set(
            'Link',
            sprintf('<%s>; rel="successor-version"', $successor),
        );

        return $response;
    }

    private function successorPath(Request $request): ?string
    {
        $path = $request->path();
        $method = $request->method();

        if ($path === 'api/customers' && $method === 'POST') {
            return '/api/v1/tenants';
        }

        if (preg_match('#^api/customers/([a-z0-9-]+)$#', $path, $m) === 1 && $method === 'DELETE') {
            return "/api/v1/tenants/{$m[1]}";
        }

        if (preg_match('#^api/customers/([a-z0-9-]+)/occ/quota/([^/]+)$#', $path, $m) === 1 && $method === 'PUT') {
            return "/api/v1/tenants/{$m[1]}/users/{$m[2]}/quota";
        }

        if (preg_match('#^api/customers/([a-z0-9-]+)/occ/branding$#', $path, $m) === 1 && $method === 'PUT') {
            return "/api/v1/tenants/{$m[1]}/branding";
        }

        if (preg_match('#^api/customers/([a-z0-9-]+)/users$#', $path, $m) === 1 && $method === 'POST') {
            return "/api/v1/tenants/{$m[1]}/users";
        }

        if (preg_match('#^api/customers/([a-z0-9-]+)/users/([^/]+)$#', $path, $m) === 1 && $method === 'DELETE') {
            return "/api/v1/tenants/{$m[1]}/users/{$m[2]}";
        }

        if (preg_match('#^api/customers/([a-z0-9-]+)/apps/enable$#', $path, $m) === 1 && $method === 'POST') {
            return "/api/v1/tenants/{$m[1]}/apps";
        }

        return null;
    }
}
