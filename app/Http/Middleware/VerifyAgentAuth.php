<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\FarmAgent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class VerifyAgentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('services.agent.transport_enabled', false)) {
            return response()->json(['error' => 'agent_transport_disabled'], 503);
        }

        $ip = $request->ip() ?? '';
        $rateKey = 'agent:'.$ip;
        if (RateLimiter::tooManyAttempts($rateKey, (int) config('services.agent.rate_limit_per_minute', 120))) {
            return response()->json(['error' => 'rate_limit'], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $farmId = $request->header('X-Farm-Id', $request->query('farm_id', ''));
        if ($farmId === '') {
            return response()->json(['error' => 'missing_farm_id'], 400);
        }

        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['error' => 'missing_token'], 401);
        }

        $agent = FarmAgent::query()
            ->where('farm_id', $farmId)
            ->where('status', 'active')
            ->first();

        if (! $agent || ! $agent->verifyToken($token)) {
            $this->auditFail('farm_agent.auth_failed', $ip, $farmId);

            return response()->json(['error' => 'invalid_agent_credentials'], 401);
        }

        $fingerprint = $request->header('X-mTLS-Cert-Fingerprint');
        if ($agent->mtls_cert_fingerprint !== null
            && $fingerprint !== null
            && ! hash_equals($agent->mtls_cert_fingerprint, $fingerprint)) {
            $this->auditFail('farm_agent.mtls_mismatch', $ip, $farmId);

            return response()->json(['error' => 'mtls_fingerprint_mismatch'], 401);
        }

        $request->attributes->set('farm_agent', $agent);

        return $next($request);
    }

    private function auditFail(string $action, string $ip, string $farmId): void
    {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => $action,
            'resource_type' => 'farm_agent',
            'resource_id' => $farmId,
            'payload' => ['ip' => $ip, 'farm_id' => $farmId],
        ]);
    }
}
