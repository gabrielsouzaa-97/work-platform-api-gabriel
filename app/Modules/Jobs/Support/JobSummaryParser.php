<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Support;

use App\Models\Job;

final class JobSummaryParser
{
    private const EMBEDDED_FAILURE_ERROR = 'occ_command_failed';

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

    /**
     * @return ?array{error: string, subcommand?: string, stdout?: string}
     */
    public function embeddedFailure(mixed $summary): ?array
    {
        return self::findEmbeddedFailure(self::logLines($summary));
    }

    public function hasEmbeddedFailure(mixed $summary): bool
    {
        return $this->embeddedFailure($summary) !== null;
    }

    public function effectiveTerminalState(string $canonical, mixed $summary): string
    {
        if ($canonical === 'success' && $this->hasEmbeddedFailure($summary)) {
            return 'partial';
        }

        return $canonical;
    }

    public static function effectiveTerminalStateFor(string $canonical, mixed $summary): string
    {
        return (new self)->effectiveTerminalState($canonical, $summary);
    }

    public static function hasEmbeddedFailureIn(mixed $summary): bool
    {
        return (new self)->hasEmbeddedFailure($summary);
    }

    public static function failureMessage(Job|array|string|null $source): string
    {
        $summary = $source instanceof Job ? $source->summary : $source;
        $lines = self::logLines($summary);

        foreach (array_reverse($lines) as $line) {
            if (str_contains($line, '[ERROR]')) {
                return self::stripLogPrefix($line, '[ERROR]');
            }
        }

        $embedded = self::findEmbeddedFailure($lines);
        if ($embedded !== null) {
            return self::formatEmbeddedFailureMessage($embedded);
        }

        if ($lines !== []) {
            return (string) end($lines);
        }

        return 'Job falhou sem detalhes.';
    }

    /**
     * @param  list<string>  $lines
     * @return ?array{error: string, subcommand?: string, stdout?: string}
     */
    private static function findEmbeddedFailure(array $lines): ?array
    {
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (! is_array($decoded) || ($decoded['error'] ?? null) !== self::EMBEDDED_FAILURE_ERROR) {
                continue;
            }

            $failure = ['error' => self::EMBEDDED_FAILURE_ERROR];
            if (isset($decoded['subcommand']) && is_string($decoded['subcommand'])) {
                $failure['subcommand'] = $decoded['subcommand'];
            }
            if (isset($decoded['stdout']) && is_string($decoded['stdout'])) {
                $failure['stdout'] = $decoded['stdout'];
            }

            return $failure;
        }

        return null;
    }

    /**
     * @param  array{error: string, subcommand?: string, stdout?: string}  $failure
     */
    private static function formatEmbeddedFailureMessage(array $failure): string
    {
        $subcommand = $failure['subcommand'] ?? 'subcomando';
        $stdout = $failure['stdout'] ?? 'sem detalhes';

        return "Subcomando {$subcommand} falhou: {$stdout}";
    }

    private static function stripLogPrefix(string $line, string $prefix): string
    {
        $trimmed = trim(str_replace($prefix, '', $line));

        return $trimmed !== '' ? $trimmed : $line;
    }
}
