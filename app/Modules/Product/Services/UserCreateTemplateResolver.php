<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Models\UserTemplate;
use App\Modules\Product\Dto\ResolvedUserCreateTemplate;

final class UserCreateTemplateResolver
{
    public function __construct(private readonly UserTemplateService $userTemplateService) {}

    /**
     * @param  list<string>  $explicitGroups
     */
    public function resolve(
        ?string $templateSlug,
        array $explicitGroups,
        ?string $explicitQuota,
    ): ResolvedUserCreateTemplate {
        if ($templateSlug === null || $templateSlug === '') {
            return new ResolvedUserCreateTemplate(
                userTemplateSlug: null,
                groups: $explicitGroups,
                quota: $explicitQuota,
            );
        }

        $template = $this->userTemplateService->findBySlug($templateSlug);

        return new ResolvedUserCreateTemplate(
            userTemplateSlug: $templateSlug,
            groups: $explicitGroups !== [] ? $explicitGroups : ($template?->groups ?? []),
            quota: $explicitQuota ?? $template?->default_quota,
        );
    }

    public function findActiveTemplate(string $slug): ?UserTemplate
    {
        $template = $this->userTemplateService->findBySlug($slug);

        if ($template === null || $template->status !== 'active') {
            return null;
        }

        return $template;
    }
}
