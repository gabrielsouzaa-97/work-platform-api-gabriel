<?php

declare(strict_types=1);

namespace App\Modules\Product\Validation;

use App\Modules\Product\Services\UserCreateTemplateResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ActiveUserTemplate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $resolver = app(UserCreateTemplateResolver::class);

        if ($resolver->findActiveTemplate($value) === null) {
            $fail('The selected user template is invalid or inactive.');
        }
    }
}
