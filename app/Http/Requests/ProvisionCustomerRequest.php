<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\Slug;
use Illuminate\Foundation\Http\FormRequest;

class ProvisionCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', new Slug, 'unique:customers,slug'],
            'cluster_server_id' => ['required', 'uuid', 'exists:cluster_servers,id'],
            'domain' => ['required', 'string', 'max:253'],
            'branding_meta' => ['sometimes', 'nullable', 'array'],
            'attachment' => ['sometimes', 'nullable', 'file', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'A customer with this slug already exists.',
        ];
    }
}
