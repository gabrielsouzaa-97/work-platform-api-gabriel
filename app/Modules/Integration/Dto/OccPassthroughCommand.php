<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

use App\Models\Customer;

final readonly class OccPassthroughCommand
{
    /**
     * @param  array<int, string>  $args
     * @param  array<string, string>  $fields
     */
    public function __construct(
        public Customer $customer,
        public OccPassthroughOperation $operation,
        public array $args = [],
        public array $fields = [],
        public int $timeoutSec = 60,
    ) {}
}
