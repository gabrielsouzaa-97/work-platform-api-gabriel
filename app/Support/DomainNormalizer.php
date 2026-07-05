<?php

declare(strict_types=1);

namespace App\Support;

final class DomainNormalizer
{
    public static function normalize(string $value): string
    {
        return rtrim(strtolower(trim($value)), '/');
    }
}
