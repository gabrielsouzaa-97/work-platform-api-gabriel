<?php

declare(strict_types=1);

namespace App\Modules\Core\Translators\Exceptions;

use RuntimeException;

/**
 * Thrown by JobTypeTranslator::cmdToCliArgv() when a canonical cmd cannot be
 * translated to upstream argv because the corresponding verb is not yet
 * implemented in `mework360-deployer-scripts` (e.g. group membership add/remove).
 *
 * Controllers/Livewire layers should catch this and surface HTTP 501 / a friendly
 * "feature pending" message instead of bubbling as a 500.
 */
final class BlockedOnUpstreamException extends RuntimeException
{
    public function __construct(string $message, public readonly string $cmd)
    {
        parent::__construct($message);
    }
}
