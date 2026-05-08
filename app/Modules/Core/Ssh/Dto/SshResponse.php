<?php

declare(strict_types=1);

namespace App\Modules\Core\Ssh\Dto;

final readonly class SshResponse
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public ?array $parsedJson = null,
    ) {}
}
