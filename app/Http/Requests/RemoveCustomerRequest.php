<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    public function rules(): array
    {
        return [
            'confirm_slug' => ['required', 'string'],
            'backup_first' => ['nullable', 'boolean'],
        ];
    }
}
