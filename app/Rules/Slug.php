<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Slug implements ValidationRule
{
    private const PATTERN = '/^[a-z0-9-]+$/';

    private const MIN_LENGTH = 3;

    private const MAX_LENGTH = 64;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $length = strlen($value);

        if ($length < self::MIN_LENGTH) {
            $fail('The :attribute must be at least '.self::MIN_LENGTH.' characters.');

            return;
        }

        if ($length > self::MAX_LENGTH) {
            $fail('The :attribute may not be greater than '.self::MAX_LENGTH.' characters.');

            return;
        }

        if (! preg_match(self::PATTERN, $value)) {
            $fail('The :attribute may only contain lowercase letters, numbers, and hyphens (no underscores or uppercase).');
        }
    }
}
