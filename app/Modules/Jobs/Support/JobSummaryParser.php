<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Support;

use App\Models\Job;

final class JobSummaryParser
{
    /**
     * @return list<string>
     */
    public static function logLines(mixed $summary): array
    {
        if ($summary === null || $summary === '' || $summary === []) {
            return [];
        }

        $raw = is_array($summary) ? $summary : explode("\n", (string) $summary);

        return array_values(array_filter(
            array_map(static fn (mixed $row): string => trim((string) $row), $raw),
            static fn (string $line): bool => $line !== '',
        ));
    }

    public static function failureMessage(Job $job): string
    {
        $lines = self::logLines($job->summary);

        foreach (array_reverse($lines) as $line) {
            if (str_contains($line, '[ERROR]')) {
                return self::stripLogPrefix($line, '[ERROR]');
            }
        }

        if ($lines !== []) {
            return (string) end($lines);
        }

        return 'Job falhou sem detalhes.';
    }

    private static function stripLogPrefix(string $line, string $prefix): string
    {
        $trimmed = trim(str_replace($prefix, '', $line));

        return $trimmed !== '' ? $trimmed : $line;
    }
}
