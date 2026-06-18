<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class BrandingStagingFiles
{
    public function __construct(
        public ?string $logoPath,
        public ?string $backgroundPath,
        public string $logoExtension,
        public string $backgroundExtension,
    ) {}
}
