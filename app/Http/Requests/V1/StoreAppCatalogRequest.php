<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAppCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_id' => ['required', 'string', 'max:100', Rule::unique('app_catalog_entries', 'app_id')],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:64'],
            'cluster_server_id' => ['nullable', 'uuid', 'exists:cluster_servers,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
