<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Modules\ClusterServers\Services\WebhookSecretValidator;
use App\Modules\Jobs\Dto\WebhookPayload;
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
            $this->securityLog('warning', 'webhook.rate_limit', ['ip' => $ip]);

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

        $signature = $request->header('X-Signature', '');
        $body = $request->getContent();

        if (! $this->validator->valid($cluster, $signature, $body)) {
            $this->auditFail('webhook_invalid_signature', $ip, $cluster->id);

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $configuredIp = trim((string) ($cluster->webhook_allowed_ip ?? ''));
        if ($configuredIp !== '' && $ip !== $configuredIp) {
            $this->auditFail('webhook_ip_not_allowed', $ip, $cluster->id);

            return response()->json(['error' => 'ip_not_allowed'], 403);
        }

        $payload = json_decode($body, true);

        // Upstream sends {job_id, state, ts, schema_version} on every callback.
        // `event` is OPTIONAL on the wire (added by upstream as an additive expansion of
        // schema_version="1"): absent payloads are treated as `job.finished` for
        // backwards compatibility with workers predating the job.started rollout.
        // `cmd`, `client`, `finished_at`, `started_at`, `exit_code`, `duration_ms`
        // are all optional and validated by `WebhookPayload::fromArray()`.
        $requiredFields = ['job_id', 'state'];
        if (! is_array($payload) || array_diff($requiredFields, array_keys($payload))) {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        // Resolve the wire-level `event` once so dedupe and replay use the same value.
        // Unknown event values (typos or future upstream versions we don't speak yet)
        // are rejected with 422 so we don't write a misleading dedupe key.
        $event = $payload['event'] ?? WebhookPayload::EVENT_FINISHED;
        if (! in_array($event, [
            WebhookPayload::EVENT_STARTED,
            WebhookPayload::EVENT_FINISHED,
        ], true)) {
            return response()->json(['error' => 'invalid_event'], 422);
        }

        // Local-env payload dump. Gated by APP_ENV=='local' so it ONLY fires on developer
        // machines — never in staging (where APP_DEBUG may be true) and never in production.
        // Placed after HMAC + struct + event enum checks so we only log payloads we've
        // already accepted as authentic — and BEFORE replay/dedupe so the developer can
        // see WHY a payload was rejected as duplicate/replay. Payload bodies do not contain
        // secrets (HMAC signature lives in the X-Signature header, not in the body), so
        // logging the decoded array is safe.
        if (app()->environment('local')) {
            try {
                Log::debug('webhook.payload_received', [
                    'cluster_server_id' => $cluster->id,
                    'ip' => $ip,
                    'event' => $event,
                    'payload' => $payload,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $replayWindow = (int) config('services.webhook.replay_window_minutes', 60);
        // Use `ts` (event timestamp, always present) for the replay window check.
        // `finished_at` is unavailable on job.started callbacks and using `now()` as
        // a fallback would silently disable the replay check for legacy payloads.
        $eventTs = Carbon::parse($payload['ts'] ?? $payload['finished_at'] ?? now()->toIso8601String());

        if ($eventTs->diffInMinutes(now()) > $replayWindow) {
            $this->auditFail('webhook_replay', $ip, $cluster->id, [
                'event' => $event,
                'ts' => $payload['ts'] ?? null,
                'finished_at' => $payload['finished_at'] ?? null,
            ]);

            return response()->json(['error' => 'replay_window_exceeded'], 422);
        }

        // Deduplicação por (job_id, event) — previne replay dentro da janela de 60 min.
        // The key is scoped per-event because a restarted worker may re-emit
        // `(job_id, job.started)` after recovering its in-flight job from Redis,
        // and that legitimate retry must not be silenced by a previous dedupe.
        // Conversely, `(job_id, job.finished)` is naturally idempotent at the
        // job level, so the per-event key remains sufficient for both flows.
        //
        // The dedupe key is set ONLY AFTER the controller succeeds (status < 300).
        // Setting it before $next() — as the original implementation did — caused
        // failed handler invocations (e.g. UnknownStateException → 422) to be
        // silenced on retry: the upstream's next attempt would hit the cache and
        // receive a fake 204, leaving the job permanently stuck in its previous
        // state with no record of why. See ISSUE-003.
        $jobId = $payload['job_id'] ?? '';
        $dedupeKey = "webhook_processed:{$jobId}:{$event}";
        $ttlSeconds = ($replayWindow + 5) * 60;

        if (Cache::has($dedupeKey)) {
            $this->auditFail('webhook_replay_duplicate', $ip, $cluster->id, [
                'job_id' => $jobId,
                'event' => $event,
            ]);

            // Idempotent: duplicate delivery from upstream → 204, not 409.
            // The job is already processed; returning 204 signals "received" without reprocessing.
            return response('', 204);
        }

        $request->attributes->set('cluster_server', $cluster);
        $request->attributes->set('webhook_payload', $payload);

        $response = $next($request);

        // Persist dedupe ONLY for responses that represent a fully-processed callback.
        // Any 4xx/5xx leaves the key absent so the upstream's retry mechanism can drive
        // a fresh attempt — important for transient errors AND for surfacing fixed bugs
        // (e.g. once a missing state is added to StateTranslator, retried callbacks land).
        if ($response->getStatusCode() < 300) {
            Cache::put($dedupeKey, true, $ttlSeconds);
        }

        return $response;
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

        $this->securityLog('warning', "webhook.{$action}", ['ip' => $ip, 'resource_id' => $resourceId]);
    }

    private function securityLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('security')->{$level}($message, $context);
        } catch (\Throwable $e) {
            // Log channel failure (e.g. permission denied on rotating file) must never
            // convert a legitimate 401/429 webhook response into a 500 error.
            report($e);
        }
    }
}
