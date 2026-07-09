<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

use App\Modules\Jobs\Support\JobSummaryParser;

final class FailureReasonSanitizer
{
    private const MAX_LENGTH = 500;

    public static function fromJobSummary(mixed $summary): string
    {
        $message = JobSummaryParser::failureMessage($summary);
        $redacted = preg_replace(
            '/\b(password|token|secret|api[_-]?key)\s*[:=]\s*\S+/i',
            '$1=[redacted]',
            $message,
        ) ?? $message;

        return mb_substr(trim($redacted), 0, self::MAX_LENGTH);
    }
}
