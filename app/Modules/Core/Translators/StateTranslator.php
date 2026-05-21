<?php

declare(strict_types=1);

namespace App\Modules\Core\Translators;

use App\Modules\Core\Translators\Exceptions\UnknownStateException;

final class StateTranslator
{
    // Two distinct upstream vocabularies converge into this map:
    //   1. Redis/state-machine values set by `set_state` inside worker.sh:
    //      queued, running, finished, failed, cancelled.
    //      Plus the docstring of nextcloud-manage §5.2 which uses `done` instead
    //      of `finished` — accepted to survive an upstream rename.
    //   2. WIRE values carried by the HTTP callback payload `state` field
    //      (worker.sh comment: "estado interno Redis = finished; payload de
    //      callback = success" — CONTRACTS.md §5.3 callback schema enum:
    //      success/failed/canceled). The callback never sends `done`/`finished`.
    //
    // The map must accept BOTH families because:
    //   - `StateTranslator` is invoked by `WebhookHandler` against the wire value
    //     (`success`/`failed`/`canceled`), AND
    //   - it is also invoked by upstream-status pollers against Redis values
    //     (`finished`/`failed`/`cancelled`).
    // Missing `success` here caused every job.finished callback to fail with
    // UnknownStateException → 422; the worker (curl -sf) translated that into
    // http_code:0 and retried until exhaustion. See FINDINGS-…
    //
    // Canonical (internal): queued, running, success, failed, cancelled
    private const MAP = [
        'queued' => 'queued',
        'running' => 'running',
        'done' => 'success',
        'finished' => 'success',
        'success' => 'success',
        'failed' => 'failed',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
    ];

    public function toCanonical(string $upstreamState): string
    {
        if ($upstreamState === '') {
            throw new UnknownStateException('Upstream state cannot be empty');
        }

        $key = strtolower(trim($upstreamState));

        return self::MAP[$key]
            ?? throw new UnknownStateException(
                "Unknown upstream state: '{$upstreamState}'. Update MAP to register new states."
            );
    }
}
