<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonSerializable;

final class TenantGroupNameRules
{
    /**
     * @return list<string|ValidationRule>
     */
    public static function forAttribute(string $attribute): array
    {
        return [
            'required',
            'string',
            'max:256',
            'regex:/^[a-zA-Z0-9._\- ]+$/',
            new ReservedAdminGroupNameRule($attribute),
        ];
    }
}

final class ReservedAdminGroupNameRule implements ValidationRule, JsonSerializable
{
    public function __construct(private readonly string $fieldAttribute)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strtolower((string) $value) === 'admin') {
            $fail('Nome de grupo reservado (admin).');
        }
    }

    /**
     * @return array{rule: string, attribute: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'rule' => 'reserved_admin',
            'attribute' => $this->fieldAttribute,
        ];
    }
}
