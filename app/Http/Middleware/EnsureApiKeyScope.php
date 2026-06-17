<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Exceptions\RenderDomainError;
use App\Modules\Core\Domain\DomainError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyScope
{
    public function handle(Request $request, Closure $next, string ...$requiredScopes): Response
    {
        $apiKey = current_api_key();

        if ($apiKey === null) {
            return $next($request);
        }

        if ($this->hasUnrestrictedScopes($apiKey->scopes)) {
            return $next($request);
        }

        if (! $this->scopesAreGranted($apiKey->scopes, $requiredScopes)) {
            return RenderDomainError::respond($request, DomainError::ForbiddenScope);
        }

        return $next($request);
    }

    /**
     * @param  array<string>|null  $scopes
     */
    private function hasUnrestrictedScopes(?array $scopes): bool
    {
        return $scopes === null || in_array('*', $scopes, true);
    }

    /**
     * @param  array<string>  $keyScopes
     * @param  array<string>  $requiredScopes
     */
    private function scopesAreGranted(array $keyScopes, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $scope) {
            if (! in_array($scope, $keyScopes, true)) {
                return false;
            }
        }

        return true;
    }
}
