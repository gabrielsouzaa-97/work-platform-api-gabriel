<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SetUserQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'quota' => ['required', 'string', 'regex:/^(\d+(\.\d+)?\s*(GB|MB|KB)|none|default)$/i'],
        ];
    }
}
