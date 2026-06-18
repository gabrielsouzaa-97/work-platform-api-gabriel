<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Modules\Onboarding\Support\OnboardingIdempotencyKey;
use App\Rules\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
