<?php

declare(strict_types=1);

namespace App\Modules\Dns\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

final class PdnsException extends RuntimeException
{
    public static function fromResponse(Response $response, string $operation): self
    {
        $status = $response->status();
        $body = $response->json('error') ?? $response->body();

        return new self("PowerDNS {$operation} failed with HTTP {$status}: {$body}");
    }
}
