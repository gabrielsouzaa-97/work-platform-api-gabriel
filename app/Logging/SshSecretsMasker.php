<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class SshSecretsMasker implements ProcessorInterface
{
    private const KEY_PATTERN = '/-----BEGIN[\s\S]*?-----END[\s\S]*?KEY-----/s';

    private const SENSITIVE_FIELDS = ['password', 'token', 'secret', 'private_key', 'passphrase'];

    private const REDACTED = '[REDACTED]';

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->maskContext($record->context);
        $extra = $this->maskContext($record->extra);
        $message = $this->maskMessage($record->message);

        return $record->with(
            message: $message,
            context: $context,
            extra: $extra,
        );
    }

    private function maskContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $context[$key] = self::REDACTED;

                continue;
            }

            if (is_string($value)) {
                $context[$key] = $this->maskMessage($value);
            } elseif (is_array($value)) {
                $context[$key] = $this->maskContext($value);
            }
        }

        return $context;
    }

    private function maskMessage(string $message): string
    {
        return preg_replace(self::KEY_PATTERN, self::REDACTED, $message) ?? $message;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE_FIELDS as $field) {
            if (str_contains($lower, $field)) {
                return true;
            }
        }

        return false;
    }
}
