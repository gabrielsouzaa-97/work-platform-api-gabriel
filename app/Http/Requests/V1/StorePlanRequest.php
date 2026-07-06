<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Rules\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', new Slug, Rule::unique('plans', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_quota' => ['required', 'string', 'max:64'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'app_ids' => ['nullable', 'array'],
            'app_ids.*' => ['string', 'max:100', Rule::exists('app_catalog_entries', 'app_id')],
        ];
    }
}
