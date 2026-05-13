<?php

declare(strict_types=1);

namespace App\Modules\Core\Ssh\Exceptions;

class SshRemoteException extends SshClientException
{
    public function __construct(
        string $message,
        public readonly int $remoteExitCode,
        public readonly bool $retryable = false,
        public readonly bool $idempotencyConflict = false,
        public readonly bool $stateConflict = false,
        public readonly bool $validationFailed = false,
        public readonly bool $notImplemented = false,
        public readonly ?array $parsedJson = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $remoteExitCode, $previous);
    }
}
