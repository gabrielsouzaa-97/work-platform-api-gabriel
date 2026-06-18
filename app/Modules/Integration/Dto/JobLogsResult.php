<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

final readonly class JobLogsResult
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(public array $lines) {}
}
