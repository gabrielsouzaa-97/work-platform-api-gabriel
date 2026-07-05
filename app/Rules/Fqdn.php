<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\DomainNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Fqdn implements ValidationRule
{
    private const PATTERN = '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (str_contains($value, '://')) {
            $fail('The :attribute must not include a protocol.');

            return;
        }

        $normalized = DomainNormalizer::normalize($value);

        if (! preg_match(self::PATTERN, $normalized)) {
            $fail('The :attribute must be a valid FQDN.');
        }
    }
}
