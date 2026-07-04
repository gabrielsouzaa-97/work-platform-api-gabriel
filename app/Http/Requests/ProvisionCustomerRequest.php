<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Integration\Services\SuiteCatalogValidator;
use App\Rules\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class ProvisionCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'operador'], true);
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (! $this->filled('slug') && $this->filled('client')) {
            $merge['slug'] = $this->input('client');
        }

        if (! $this->filled('domain') && $this->filled('fqdn')) {
            $merge['domain'] = $this->input('fqdn');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'slug' => ['required_without:client', 'string', new Slug, Rule::unique('customers', 'slug')->whereNull('deleted_at')],
            'client' => ['sometimes', 'string', new Slug],
            'cluster_server_id' => ['required_without:auto_place', 'uuid', 'exists:cluster_servers,id'],
            'auto_place' => ['sometimes', 'boolean'],
            'tier' => ['sometimes', 'string', 'in:shared,dedicated'],
            'domain' => ['required_without:fqdn', 'string', 'max:253'],
            'fqdn' => ['sometimes', 'string', 'max:253'],
            'apps' => ['nullable', 'array'],
            'apps.*' => ['string', 'max:100'],
            'full_apps' => ['nullable', 'boolean'],
            'shell' => ['sometimes', 'boolean'],
            'suite_catalog' => ['sometimes', 'boolean'],
            'image_mode' => ['sometimes', 'boolean'],
            'legacy_vendor' => ['sometimes', 'boolean'],
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

            if (! $this->usesSuiteCatalogMode()) {
                return;
            }

            $apps = $this->input('apps', []);
            if (! is_array($apps) || $apps === []) {
                return;
            }

            try {
                app(SuiteCatalogValidator::class)->validateAppIds($apps);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $v->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    public function usesSuiteCatalogMode(): bool
    {
        if ($this->boolean('legacy_vendor', false) || $this->boolean('full_apps', false)) {
            return false;
        }

        if ($this->has('suite_catalog')) {
            return $this->boolean('suite_catalog');
        }

        return (bool) config('platform.suite_catalog.default_mode', true);
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use apenas letras minúsculas, números e hífen.',
            'slug.unique' => 'Slug já em uso.',
        ];
    }
}
