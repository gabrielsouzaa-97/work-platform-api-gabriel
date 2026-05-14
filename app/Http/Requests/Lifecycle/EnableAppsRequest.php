<?php

declare(strict_types=1);

namespace App\Http\Requests\Lifecycle;

use Illuminate\Foundation\Http\FormRequest;

class EnableAppsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    public function rules(): array
    {
        return [
            'apps' => ['required', 'array', 'min:1'],
            'apps.*' => ['string', 'regex:/^[a-z0-9_]+$/', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'apps.*.regex' => 'App ID deve conter apenas letras minúsculas, números e underscore.',
        ];
    }
}
