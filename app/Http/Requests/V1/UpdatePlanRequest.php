<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_quota' => ['sometimes', 'string', 'max:64'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_apps' => ['nullable', 'integer', 'min:1'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'app_ids' => ['nullable', 'array'],
            'app_ids.*' => ['string', 'max:100', Rule::exists('app_catalog_entries', 'app_id')],
        ];
    }
}
