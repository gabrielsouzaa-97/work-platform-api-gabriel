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
        public string $tier = 'shared',
        public bool $shell = true,
        public bool $suiteCatalog = true,
        public bool $legacyVendor = false,
        public bool $imageMode = false,
        public ?string $planSlug = null,
        /** @var array{enabled: bool, bucket: ?string}|null */
        public ?array $objectstore = null,
    ) {}

    public function usesObjectstore(): bool
    {
        return $this->objectstore !== null && ($this->objectstore['enabled'] ?? false);
    }

    public function objectstoreBucket(): ?string
    {
        $bucket = $this->objectstore['bucket'] ?? null;

        return is_string($bucket) && $bucket !== '' ? $bucket : null;
    }

    public function usesSuiteCatalog(): bool
    {
        if ($this->legacyVendor || $this->fullApps) {
            return false;
        }

        return $this->suiteCatalog;
    }

    public function usesImageMode(): bool
    {
        return $this->imageMode;
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromRequestWithCustomer($request, null);
    }

    public static function fromRequestWithCustomer(Request $request, ?Customer $customer): self
    {
        $suiteCatalog = $request->has('suite_catalog')
            ? $request->boolean('suite_catalog')
            : (bool) config('platform.suite_catalog.default_mode', true);

        $imageMode = $request->has('image_mode')
            ? $request->boolean('image_mode')
            : (bool) config('platform.image_mode.default_mode', false);

        return new self(
            slug: $request->string('slug')->toString(),
            domain: $request->string('domain')->toString(),
            clusterServerId: $request->string('cluster_server_id')->toString(),
            apps: $request->input('apps', []) ?? [],
            fullApps: $request->boolean('full_apps', false),
            logoPath: self::resolveBrandingPath($request, 'logo', $customer, 'logo_path'),
            backgroundPath: self::resolveBrandingPath($request, 'background', $customer, 'background_path'),
            mail: self::resolveMailPayload($request),
            tier: $request->string('tier', 'shared')->toString(),
            shell: $request->has('shell') ? $request->boolean('shell') : true,
            suiteCatalog: $suiteCatalog,
            legacyVendor: $request->boolean('legacy_vendor', false),
            imageMode: $imageMode,
            planSlug: $request->filled('plan_slug') ? $request->string('plan_slug')->toString() : null,
            objectstore: self::resolveObjectstorePayload($request),
        );
    }

    /**
     * @return array{enabled: bool, bucket: ?string}|null
     */
    private static function resolveObjectstorePayload(Request $request): ?array
    {
        if (! $request->has('objectstore')) {
            return null;
        }

        $objectstore = $request->input('objectstore');
        if (! is_array($objectstore)) {
            return null;
        }

        $bucket = $objectstore['bucket'] ?? null;

        return [
            'enabled' => (bool) ($objectstore['enabled'] ?? false),
            'bucket' => is_string($bucket) && $bucket !== '' ? $bucket : null,
        ];
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
