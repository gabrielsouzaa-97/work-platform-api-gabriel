<?php

declare(strict_types=1);

namespace App\Modules\Core\Translators;

use App\Modules\Core\Translators\Exceptions\UnknownStateException;

final class StateTranslator
{
    // Upstream states (per nextcloud-manage §5.2 docstring): queued, running, done, failed, cancelled
    // Upstream worker.sh (real implementation) emits: queued, running, finished, failed, cancelled
    //   — see mework360-deployer-scripts/scripts/worker.sh:609,621 (`set_state "$jid" finished`).
    // The contract docs say "done" but the implementation says "finished"; we accept BOTH so an
    // upstream renaming to align with the docstring won't break us. Tracked in upstream issue.
    //
    // Canonical (internal): queued, running, success, failed, cancelled
    private const MAP = [
        'queued' => 'queued',
        'running' => 'running',
        'done' => 'success',
        'finished' => 'success',
        'failed' => 'failed',
        'cancelled' => 'cancelled',
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
