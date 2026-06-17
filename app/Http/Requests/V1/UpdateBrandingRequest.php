<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
}
