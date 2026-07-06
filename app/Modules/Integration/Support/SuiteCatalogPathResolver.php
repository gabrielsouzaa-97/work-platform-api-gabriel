<?php

declare(strict_types=1);

namespace App\Modules\Integration\Support;

use RuntimeException;

final class SuiteCatalogPathResolver
{
    public static function resolve(?string $path = null): string
    {
        $candidates = [
            $path,
            config('platform.suite_catalog.path'),
            storage_path('app/suite_catalog.json'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Suite catalog JSON is not readable.');
    }
}
