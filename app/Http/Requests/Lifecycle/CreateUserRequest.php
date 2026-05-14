<?php

declare(strict_types=1);

namespace App\Http\Requests\Lifecycle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'max:64'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'email' => ['nullable', 'email', 'max:255'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', 'max:256'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username deve conter apenas letras, números, ponto, hífen ou underscore.',
        ];
    }
}
