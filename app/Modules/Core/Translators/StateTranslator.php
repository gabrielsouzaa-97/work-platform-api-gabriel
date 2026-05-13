<?php

declare(strict_types=1);

namespace App\Modules\Core\Translators;

use App\Modules\Core\Translators\Exceptions\UnknownStateException;

final class StateTranslator
{
    // Upstream states (nextcloud-manage §5.2): queued, running, done, failed, cancelled
    // Canonical (internal): queued, running, success, failed, cancelled
    // Only 'done' → 'success' requires renaming; all others are identity.
    private const MAP = [
        'queued' => 'queued',
        'running' => 'running',
        'done' => 'success',
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
