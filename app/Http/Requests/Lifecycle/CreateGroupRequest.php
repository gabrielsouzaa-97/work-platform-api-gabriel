<?php

declare(strict_types=1);

namespace App\Http\Requests\Lifecycle;

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
            'name' => [
                'required',
                'string',
                'max:256',
                'regex:/^[a-zA-Z0-9._\- ]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (strtolower((string) $value) === 'admin') {
                        $fail('Nome de grupo reservado (admin).');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'O nome do grupo aceita apenas letras, números, espaços e os caracteres . _ -',
        ];
    }
}
