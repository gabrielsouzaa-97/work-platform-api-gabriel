<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

use App\Models\ClusterServer;
use App\Models\Customer;

final readonly class EnableAppsCommand
{
    /**
     * @param  list<string>  $manageArgs  Fully-built nextcloud-manage argv (slug + verb + flags).
     */
    public function __construct(
        public Customer $customer,
        public ClusterServer $cluster,
        public array $manageArgs,
    ) {}
}
