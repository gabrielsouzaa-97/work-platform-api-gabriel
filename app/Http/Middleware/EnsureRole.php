<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || $user->status !== 'active' || ! in_array($user->role, $roles, true)) {
            abort(403, 'Acesso negado.');
        }

        return $next($request);
    }
}
