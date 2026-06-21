<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Modules\Billing\Actions\ProcessWhmcsWebhookAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WhmcsWebhookController extends Controller
{
    public function __construct(
        private readonly ProcessWhmcsWebhookAction $action,
    ) {}

    public function receive(Request $request): JsonResponse
    {
        if (! config('whmcs.enabled')) {
            return response()->json(['error' => 'whmcs_disabled'], 503);
        }

        $body = $request->getContent();
        $signature = (string) $request->header('X-Whmcs-Signature', '');

        if (! $this->validSignature($body, $signature)) {
            $this->securityLog('warning', 'whmcs_webhook.invalid_signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($body, true);

        if (! is_array($payload) || ! isset($payload['event'])) {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        try {
            $result = $this->action->execute($payload, $this->resolveActor());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid_payload', 'message' => $e->getMessage()], 422);
        }

        return response()->json($result, 200);
    }

    private function validSignature(string $body, string $signature): bool
    {
        $secret = (string) config('whmcs.webhook_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $body, $secret);
        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals($expected, $provided);
    }

    private function resolveActor(): Operator
    {
        return Operator::query()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function securityLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('security')->{$level}($message, $context);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
