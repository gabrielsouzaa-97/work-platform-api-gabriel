<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

use App\Models\Customer;

final readonly class SetBrandingCommand
{
    /**
     * @param  array<string, string>  $fields  Theming keys (name, color, url, …).
     */
    public function __construct(
        public Customer $customer,
        public array $fields,
    ) {}
}
