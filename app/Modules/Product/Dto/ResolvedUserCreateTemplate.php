<?php

declare(strict_types=1);

namespace App\Modules\Product\Dto;

final class ResolvedUserCreateTemplate
{
    /**
     * @param  list<string>  $groups
     */
    public function __construct(
        public readonly ?string $userTemplateSlug,
        public readonly array $groups,
        public readonly ?string $quota,
    ) {}
}
