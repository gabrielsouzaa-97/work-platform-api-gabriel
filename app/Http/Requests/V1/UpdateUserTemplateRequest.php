<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Modules\Product\Validation\PermissionsSchemaV1;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserTemplateRequest extends FormRequest
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
            'default_quota' => ['nullable', 'string', 'max:64'],
            'groups' => ['sometimes', 'array'],
            'groups.*' => ['string', 'max:256'],
            'permissions' => ['sometimes', 'array', new PermissionsSchemaV1],
            'permissions.schema_version' => ['required_with:permissions', 'integer', 'in:1'],
            'permissions.users.hire' => ['required_with:permissions', 'boolean'],
            'permissions.users.block' => ['required_with:permissions', 'boolean'],
            'permissions.users.activate' => ['required_with:permissions', 'boolean'],
            'permissions.apps.install_from_store' => ['required_with:permissions', 'boolean'],
            'permissions.apps.create_integration' => ['required_with:permissions', 'boolean'],
            'permissions.audit.read' => ['required_with:permissions', 'boolean'],
            'app_ids' => ['sometimes', 'array'],
            'app_ids.*' => ['string', 'max:100', Rule::exists('app_catalog_entries', 'app_id')],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
