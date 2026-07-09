<?php

declare(strict_types=1);

namespace App\Modules\Customers\Validation;

use App\Models\TenantGroup;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class TenantGroupMembership implements ValidationRule
{
    public function __construct(
        private readonly string $customerSlug,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (strtolower($value) === 'admin') {
            $fail('Grupo admin é reservado da plataforma.');

            return;
        }

        $exists = TenantGroup::query()
            ->where('customer_slug', $this->customerSlug)
            ->whereRaw('LOWER(name) = ?', [strtolower($value)])
            ->exists();

        if (! $exists) {
            $fail("Grupo '{$value}' não encontrado. Crie o grupo antes de atribuí-lo.");
        }
    }
}
