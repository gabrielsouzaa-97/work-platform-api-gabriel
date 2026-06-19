<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProvisionCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', new Slug, Rule::unique('customers', 'slug')->whereNull('deleted_at')],
            'cluster_server_id' => ['required', 'uuid', 'exists:cluster_servers,id'],
            'domain' => ['required', 'string', 'max:253'],
            'apps' => ['nullable', 'array'],
            'apps.*' => ['string', 'max:100'],
            'full_apps' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'background' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'mail' => ['nullable', 'array'],
            'mail.provision_domain' => ['nullable', 'boolean'],
            'mail.default_mailbox' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            foreach (['logo', 'background'] as $field) {
                if (! $this->hasFile($field)) {
                    continue;
                }
                $mime = $this->file($field)->getMimeType();
                if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
                    $v->errors()->add($field, 'Tipo de imagem inválido (mime real).');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use apenas letras minúsculas, números e hífen.',
            'slug.unique' => 'Slug já em uso.',
        ];
    }
}
