<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantBinding
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = current_api_key();

        if ($apiKey === null || $apiKey->allowed_tenant_slugs === null) {
            return $next($request);
        }

        $slug = $this->resolveCustomerSlug($request);

        if ($slug === null || ! in_array($slug, $apiKey->allowed_tenant_slugs, true)) {
            return response()->json(['error' => 'forbidden_tenant'], 403);
        }

        return $next($request);
    }

    private function resolveCustomerSlug(Request $request): ?string
    {
        $customer = $request->route('customer');

        if ($customer instanceof Customer) {
            return $customer->slug;
        }

        if (is_string($customer) && $customer !== '') {
            return $customer;
        }

        $slug = $request->route('slug');

        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        if ($request->routeIs('api.customers.store')) {
            $bodySlug = $request->input('slug');

            if (is_string($bodySlug) && $bodySlug !== '') {
                return $bodySlug;
            }
        }

        return null;
    }
}
