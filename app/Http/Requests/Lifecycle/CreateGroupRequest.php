<?php

declare(strict_types=1);

namespace App\Http\Requests\Lifecycle;

use App\Modules\Customers\Support\TenantGroupNameRules;
use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    public function rules(): array
    {
        return [
            'name' => TenantGroupNameRules::forAttribute('name'),
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'O nome do grupo aceita apenas letras, números, espaços e os caracteres . _ -',
        ];
    }
}
