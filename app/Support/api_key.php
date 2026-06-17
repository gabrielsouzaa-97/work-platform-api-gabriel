<?php

declare(strict_types=1);

use App\Models\ApiKey;

function current_api_key(): ?ApiKey
{
    $apiKey = request()->attributes->get('api_key');

    return $apiKey instanceof ApiKey ? $apiKey : null;
}
