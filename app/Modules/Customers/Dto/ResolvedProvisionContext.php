<?php

declare(strict_types=1);

namespace App\Modules\Customers\Dto;

use App\Http\Requests\ProvisionCustomerRequest;
use App\Http\Requests\V1\CreateOnboardingRequest;

final readonly class ResolvedProvisionContext
{
    /**
     * @param  list<string>  $resolvedApps
     */
    public function __construct(
        public bool $imageMode,
        public bool $suiteCatalog,
        public bool $fullApps,
        public bool $legacyVendor,
        public array $resolvedApps,
        public ?string $planSlug = null,
        public ?string $clusterServerId = null,
    ) {}

    public static function fromProvisionCustomerRequest(ProvisionCustomerRequest $request): self
    {
        $apps = $request->input('apps', []);
        $apps = is_array($apps) ? $apps : [];

        return new self(
            imageMode: $request->resolvesImageMode(),
            suiteCatalog: $request->usesSuiteCatalogMode(),
            fullApps: $request->boolean('full_apps', false),
            legacyVendor: $request->boolean('legacy_vendor', false),
            resolvedApps: $apps,
            planSlug: $request->filled('plan_slug') ? $request->string('plan_slug')->toString() : null,
            clusterServerId: $request->filled('cluster_server_id')
                ? $request->string('cluster_server_id')->toString()
                : null,
        );
    }

    /**
     * @param  list<string>  $resolvedApps
     */
    public static function fromProvisionPayload(ProvisionPayload $payload, array $resolvedApps): self
    {
        return new self(
            imageMode: $payload->usesImageMode(),
            suiteCatalog: $payload->usesSuiteCatalog(),
            fullApps: $payload->fullApps,
            legacyVendor: $payload->legacyVendor,
            resolvedApps: $resolvedApps,
            planSlug: $payload->planSlug,
            clusterServerId: $payload->clusterServerId,
        );
    }

    /**
     * @param  list<string>  $resolvedApps
     */
    public static function fromOnboardingRequest(CreateOnboardingRequest $request, array $resolvedApps): self
    {
        return new self(
            imageMode: $request->resolvesTenantImageMode(),
            suiteCatalog: $request->usesTenantSuiteCatalogMode(),
            fullApps: $request->boolean('tenant.full_apps', false),
            legacyVendor: $request->boolean('tenant.legacy_vendor', false),
            resolvedApps: $resolvedApps,
            planSlug: $request->filled('tenant.plan_slug')
                ? $request->string('tenant.plan_slug')->toString()
                : null,
            clusterServerId: $request->string('tenant.cluster_server_id')->toString(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $resolvedApps
     */
    public static function fromWhmcsPayload(array $payload, array $resolvedApps): self
    {
        $imageMode = array_key_exists('image_mode', $payload)
            ? filter_var($payload['image_mode'], FILTER_VALIDATE_BOOLEAN)
            : (bool) config('platform.image_mode.default_mode', false);

        $legacyVendor = filter_var($payload['legacy_vendor'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $fullApps = filter_var($payload['full_apps'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $suiteCatalog = self::resolveWhmcsSuiteCatalog($payload, $legacyVendor, $fullApps);

        $planSlug = isset($payload['plan_slug']) && is_string($payload['plan_slug']) && $payload['plan_slug'] !== ''
            ? $payload['plan_slug']
            : null;

        $clusterServerId = isset($payload['cluster_server_id']) && is_string($payload['cluster_server_id'])
            ? $payload['cluster_server_id']
            : null;

        return new self(
            imageMode: $imageMode,
            suiteCatalog: $suiteCatalog,
            fullApps: $fullApps,
            legacyVendor: $legacyVendor,
            resolvedApps: $resolvedApps,
            planSlug: $planSlug,
            clusterServerId: $clusterServerId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolveWhmcsSuiteCatalog(array $payload, bool $legacyVendor, bool $fullApps): bool
    {
        if ($legacyVendor || $fullApps) {
            return false;
        }

        if (array_key_exists('suite_catalog', $payload)) {
            return filter_var($payload['suite_catalog'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('platform.suite_catalog.default_mode', true);
    }
}
