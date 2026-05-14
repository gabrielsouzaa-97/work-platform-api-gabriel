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
            'name' => ['required', 'string', 'max:256'],
        ];
    }
}
