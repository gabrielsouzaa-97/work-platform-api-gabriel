<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Modules\ClusterServers\Services\WebhookSecretValidator;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookHmac
{
    public function __construct(
        private readonly WebhookSecretValidator $validator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip() ?? '';

        $rateKey = "webhook:{$ip}";
        $limit = (int) config('services.webhook.rate_limit_per_minute', 100);

        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            Log::channel('security')->warning('webhook.rate_limit', ['ip' => $ip]);

            return response()->json(['error' => 'rate_limit'], 429);
        }
        RateLimiter::hit($rateKey, 60);

        // Accept cluster id from query param (?cluster=<uuid>) — upstream does not send
        // X-Cluster-Server-Id as a header; embedding it in the callback URL is the canonical approach.
        // Header is kept as fallback for backwards compatibility.
        $clusterId = $request->query('cluster', $request->header('X-Cluster-Server-Id', ''));
        if (! $clusterId || ! Str::isUuid($clusterId)) {
            return response()->json(['error' => 'invalid_cluster_id'], 400);
        }

        $cluster = ClusterServer::find($clusterId);
        if (! $cluster) {
            $this->auditFail('webhook_unknown_cluster', $ip, $clusterId);

            return response()->json(['error' => 'unknown_cluster'], 401);
        }

        $allowedIp = Cache::remember(
            "webhook_ip:{$cluster->id}",
            300,
            fn () => gethostbyname($cluster->ssh_host)
        );

        if ($ip !== $allowedIp) {
            $this->auditFail('webhook_ip_mismatch', $ip, $cluster->id);

            return response()->json(['error' => 'ip_not_whitelisted'], 401);
        }

        $signature = $request->header('X-Signature', '');
        $body = $request->getContent();

        if (! $this->validator->valid($cluster, $signature, $body)) {
            $this->auditFail('webhook_invalid_signature', $ip, $cluster->id);

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = json_decode($body, true);

        // Upstream sends only {job_id, state, ts, schema_version}; cmd/client/finished_at are optional.
        $requiredFields = ['job_id', 'state'];
        if (! is_array($payload) || array_diff($requiredFields, array_keys($payload))) {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        $replayWindow = (int) config('services.webhook.replay_window_minutes', 60);
        // Upstream uses "ts" as the event timestamp; "finished_at" kept for forward compatibility.
        $finishedAt = Carbon::parse($payload['finished_at'] ?? $payload['ts'] ?? now()->toIso8601String());

        if ($finishedAt->diffInMinutes(now()) > $replayWindow) {
            $this->auditFail('webhook_replay', $ip, $cluster->id, [
                'finished_at' => $payload['finished_at'],
            ]);

            return response()->json(['error' => 'replay_window_exceeded'], 422);
        }

        // Deduplicação por job_id — previne replay dentro da janela de 60 min.
        $jobId = $payload['job_id'] ?? '';
        $dedupeKey = "webhook_processed:{$jobId}";
        $ttlSeconds = ($replayWindow + 5) * 60;

        if (Cache::has($dedupeKey)) {
            $this->auditFail('webhook_replay_duplicate', $ip, $cluster->id, [
                'job_id' => $jobId,
            ]);

            // Idempotent: duplicate delivery from upstream → 204, not 409.
            // The job is already processed; returning 204 signals "received" without reprocessing.
            return response('', 204);
        }

        Cache::put($dedupeKey, true, $ttlSeconds);

        $request->attributes->set('cluster_server', $cluster);
        $request->attributes->set('webhook_payload', $payload);

        return $next($request);
    }

    private function auditFail(string $action, string $ip, string $resourceId, array $extra = []): void
    {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => $action,
            'resource_type' => 'webhook',
            'resource_id' => $resourceId,
            'payload' => array_merge(['ip' => $ip], $extra),
            'ip' => $ip,
        ]);

        Log::channel('security')->warning("webhook.{$action}", ['ip' => $ip, 'resource_id' => $resourceId]);
    }
}
