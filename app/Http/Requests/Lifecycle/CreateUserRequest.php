<?php

declare(strict_types=1);

namespace App\Http\Requests\Lifecycle;

use App\Modules\Product\Validation\ActiveUserTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    protected function prepareForValidation(): void
    {
        // OpenAPI historically documented `displayname` / `subadmin`; upstream uses snake_case.
        if (! $this->has('display_name') && $this->has('displayname')) {
            $this->merge(['display_name' => $this->input('displayname')]);
        }

        if (! $this->has('subadmin_groups') && $this->has('subadmin')) {
            $this->merge(['subadmin_groups' => $this->input('subadmin')]);
        }
    }

    public function rules(): array
    {
        $forbiddenAdminGroup = function (string $attribute, mixed $value, \Closure $fail): void {
            if (strtolower((string) $value) === 'admin') {
                $fail('Grupo admin é reservado da plataforma.');
            }
        };

        return [
            'username' => [
                'required',
                'string',
                'regex:/^[a-zA-Z0-9._-]+$/',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (strtolower((string) $value) === 'admin') {
                        $fail('Username reservado (criado no provisionamento).');
                    }
                },
            ],
            'password' => ['required', 'string', Password::min(10)->letters()->numbers()],
            'display_name' => ['nullable', 'string', 'max:255'],
            'displayname' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'quota' => ['nullable', 'string', 'regex:/^(\d+(\.\d+)?\s*(GB|MB|KB)|\d+(GB|MB|KB)|none|default|unlimited)$/i'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', 'max:256', $forbiddenAdminGroup],
            'subadmin_groups' => ['nullable', 'array'],
            'subadmin_groups.*' => ['string', 'max:256', $forbiddenAdminGroup],
            'subadmin' => ['nullable', 'array'],
            'subadmin.*' => ['string', 'max:256', $forbiddenAdminGroup],
            'user_template_slug' => ['nullable', 'string', 'max:64', new ActiveUserTemplate()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username deve conter apenas letras, números, ponto, hífen ou underscore.',
            'quota.regex' => 'Formato inválido. Use N GB, NGB, none, default ou unlimited.',
        ];
    }
}
