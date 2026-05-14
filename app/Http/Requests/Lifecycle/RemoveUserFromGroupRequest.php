<?php

declare(strict_types=1);

namespace App\Http\Requests\Lifecycle;

use Illuminate\Foundation\Http\FormRequest;

class RemoveUserFromGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    protected function prepareForValidation(): void
    {
        // username comes from the route segment, not the request body (DELETE has no body)
        $this->merge(['username' => $this->route('username')]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'max:64'],
        ];
    }
}
