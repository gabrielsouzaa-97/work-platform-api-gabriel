<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Modules\Customers\Dto\ResolvedProvisionContext;
use App\Modules\Customers\Validation\ProvisioningReadinessValidator;
use App\Modules\Integration\Services\SuiteCatalogValidator;
use App\Modules\Onboarding\Support\OnboardingIdempotencyKey;
use App\Modules\Product\Services\PlanAppResolver;
use App\Rules\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final class CreateOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $slugRules = ['required', 'string', new Slug];

        if (! $this->isIdempotentReplay()) {
            $slugRules[] = Rule::unique('customers', 'slug')->whereNull('deleted_at');
        }

        return [
            'tenant' => ['required', 'array'],
            'tenant.slug' => $slugRules,
            'tenant.domain' => ['required', 'string', 'max:253'],
            'tenant.cluster_server_id' => ['required', 'uuid', 'exists:cluster_servers,id'],
            'tenant.image_mode' => ['sometimes', 'boolean'],
            'tenant.suite_catalog' => ['sometimes', 'boolean'],
            'tenant.plan_slug' => ['nullable', 'string', Rule::exists('plans', 'slug')->where('status', 'active')],
            'tenant.full_apps' => ['sometimes', 'boolean'],
            'tenant.legacy_vendor' => ['sometimes', 'boolean'],
            'admin' => ['required', 'array'],
            'admin.username' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'max:64'],
            'admin.password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'admin.email' => ['required', 'email', 'max:255'],
            'apps_enabled' => ['required', 'array', 'min:1'],
            'apps_enabled.*' => ['string', 'max:100'],
            'branding' => ['nullable', 'array'],
            'branding.name' => ['nullable', 'string', 'max:255'],
            'branding.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'branding.url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $planSlug = $this->filled('tenant.plan_slug')
                ? $this->string('tenant.plan_slug')->toString()
                : null;
            $apps = $this->input('apps_enabled', []);
            $apps = is_array($apps) ? $apps : [];

            try {
                $resolvedApps = app(PlanAppResolver::class)->resolve($planSlug, $apps);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $v->errors()->add($field, $message);
                    }
                }

                return;
            }

            if ($this->usesTenantSuiteCatalogMode() && $resolvedApps !== []) {
                try {
                    app(SuiteCatalogValidator::class)->validateAppIds($resolvedApps);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        $targetField = str_starts_with($field, 'apps.') ? 'apps_enabled' : $field;

                        foreach ($messages as $message) {
                            $v->errors()->add($targetField, $message);
                        }
                    }

                    return;
                }
            }

            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $context = ResolvedProvisionContext::fromOnboardingRequest($this, $resolvedApps);

            try {
                app(ProvisioningReadinessValidator::class)->assertValid($context);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $v->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    public function resolvesTenantImageMode(): bool
    {
        if ($this->has('tenant.image_mode')) {
            return $this->boolean('tenant.image_mode');
        }

        return (bool) config('platform.image_mode.default_mode', false);
    }

    public function usesTenantSuiteCatalogMode(): bool
    {
        if ($this->boolean('tenant.legacy_vendor', false) || $this->boolean('tenant.full_apps', false)) {
            return false;
        }

        if ($this->has('tenant.suite_catalog')) {
            return $this->boolean('tenant.suite_catalog');
        }

        return (bool) config('platform.suite_catalog.default_mode', true);
    }

    public function messages(): array
    {
        return [
            'tenant.slug.unique' => 'Slug já em uso.',
            'admin.username.regex' => 'Username deve conter apenas letras, números, ponto, hífen ou underscore.',
        ];
    }

    private function isIdempotentReplay(): bool
    {
        $slug = $this->input('tenant.slug');
        $domain = $this->input('tenant.domain');
        $username = $this->input('admin.username');

        if (! is_string($slug) || ! is_string($domain) || ! is_string($username)) {
            return false;
        }

        $hash = OnboardingIdempotencyKey::hash([
            'slug' => $slug,
            'domain' => $domain,
            'admin_username' => $username,
        ]);

        return OnboardingIdempotencyKey::findRecentReplay($hash) !== null;
    }
}
