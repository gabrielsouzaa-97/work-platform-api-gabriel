<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Dto;

final readonly class OnboardingSpec
{
    public function __construct(
        public string $tenantSlug,
        public string $domain,
        public string $clusterServerId,
        public array $apps,
        public bool $fullApps,
        public string $adminUsername,
        public string $adminPassword,
        public string $adminEmail,
        public string $adminDisplayName,
        public ?array $brandingFields = null,
        public ?string $logoPath = null,
        public ?string $backgroundPath = null,
    ) {}
}
