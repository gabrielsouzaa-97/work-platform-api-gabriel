<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

final class TenantGroupNameRules
{
    /** @var list<string|\Closure>|null */
    private static ?array $rules = null;

    /**
     * @return list<string|\Closure>
     */
    public static function forAttribute(string $attribute): array
    {
        if (self::$rules === null) {
            self::$rules = [
                'required',
                'string',
                'max:256',
                'regex:/^[a-zA-Z0-9._\- ]+$/',
                static function (string $_attribute, mixed $value, \Closure $fail): void {
                    if (strtolower((string) $value) === 'admin') {
                        $fail('Nome de grupo reservado (admin).');
                    }
                },
            ];
        }

        return self::$rules;
    }
}
