<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

use App\Models\ClusterServer;

final readonly class ManageAsyncCommand
{
    /**
     * @param  list<string>  $manageArgs
     */
    public function __construct(
        public ClusterServer $cluster,
        public array $manageArgs,
        public ?string $stdinJson,
    ) {}
}
