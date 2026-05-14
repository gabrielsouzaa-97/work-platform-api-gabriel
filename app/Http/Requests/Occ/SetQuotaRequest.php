<?php

declare(strict_types=1);

namespace App\Http\Requests\Occ;

use Illuminate\Foundation\Http\FormRequest;

class SetQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    public function rules(): array
    {
        return [
            'quota' => ['required', 'string', 'regex:/^(\d+(\.\d+)?\s*(GB|MB|KB)|none|default)$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'quota.regex' => 'Formato inválido. Use N GB, N MB, N KB, none ou default.',
        ];
    }
}
