<?php

declare(strict_types=1);

namespace App\Modules\Customers\Dto;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final readonly class ProvisionPayload
{
    public function __construct(
        public string $slug,
        public string $domain,
        public string $clusterServerId,
        public array $apps,
        public bool $fullApps,
        public ?string $logoPath,
        public ?string $backgroundPath,
        public ?array $mail = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromRequestWithCustomer($request, null);
    }

    public static function fromRequestWithCustomer(Request $request, ?Customer $customer): self
    {
        return new self(
            slug: $request->string('slug')->toString(),
            domain: $request->string('domain')->toString(),
            clusterServerId: $request->string('cluster_server_id')->toString(),
            apps: $request->input('apps', []) ?? [],
            fullApps: $request->boolean('full_apps', false),
            logoPath: self::resolveBrandingPath($request, 'logo', $customer, 'logo_path'),
            backgroundPath: self::resolveBrandingPath($request, 'background', $customer, 'background_path'),
            mail: self::resolveMailPayload($request),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolveMailPayload(Request $request): ?array
    {
        $mail = $request->input('mail');
        if (! is_array($mail) || $mail === []) {
            return null;
        }

        return $mail;
    }

    private static function resolveBrandingPath(
        Request $request,
        string $field,
        ?Customer $customer,
        string $metaKey
    ): ?string {
        if ($request->hasFile($field)) {
            return $request->file($field)->getRealPath();
        }

        $relativePath = ($customer?->branding_meta ?? [])[$metaKey] ?? null;
        if (! is_string($relativePath) || $relativePath === '') {
            return null;
        }

        return Storage::disk('local')->exists($relativePath)
            ? Storage::disk('local')->path($relativePath)
            : null;
    }
}
