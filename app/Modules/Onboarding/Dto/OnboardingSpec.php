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
        public string $adminEmail,
        public string $adminDisplayName,
        public ?string $logoPath = null,
        public ?string $backgroundPath = null,
    ) {}
}
