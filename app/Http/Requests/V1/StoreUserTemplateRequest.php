<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Modules\Product\Validation\PermissionsSchemaV1;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:64', Rule::unique('user_templates', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_quota' => ['nullable', 'string', 'max:64'],
            'groups' => ['required', 'array'],
            'groups.*' => ['string', 'max:256'],
            'permissions' => ['required', 'array', new PermissionsSchemaV1()],
            'permissions.schema_version' => ['required', 'integer', 'in:1'],
            'permissions.users.hire' => ['required', 'boolean'],
            'permissions.users.block' => ['required', 'boolean'],
            'permissions.users.activate' => ['required', 'boolean'],
            'permissions.apps.install_from_store' => ['required', 'boolean'],
            'permissions.apps.create_integration' => ['required', 'boolean'],
            'permissions.audit.read' => ['required', 'boolean'],
            'app_ids' => ['sometimes', 'array'],
            'app_ids.*' => ['string', 'max:100', Rule::exists('app_catalog_entries', 'app_id')],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
