<?php

declare(strict_types=1);

namespace App\Modules\Core\Translators;

use App\Modules\Core\Translators\Exceptions\UnknownStateException;

final class StateTranslator
{
    private const MAP = [
        'pending' => 'queued',
        'running' => 'running',
        'done' => 'success',
        'error' => 'failed',
        'aborted' => 'cancelled',
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
