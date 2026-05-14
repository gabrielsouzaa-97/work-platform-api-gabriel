<?php

declare(strict_types=1);

namespace App\Http\Requests\Occ;

use Illuminate\Foundation\Http\FormRequest;

class SetBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'url' => ['nullable', 'url', 'max:2048'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'imprintUrl' => ['nullable', 'url', 'max:2048'],
            'privacyUrl' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'Cor deve ser hexadecimal no formato #RRGGBB.',
        ];
    }
}
