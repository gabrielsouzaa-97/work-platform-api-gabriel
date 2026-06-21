<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

final class WhmcsApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromWhmcsResult(array $payload, string $operation): self
    {
        $message = (string) ($payload['message'] ?? 'unknown WHMCS error');

        return new self("WHMCS {$operation} failed: {$message}");
    }

    public static function fromResponse(Response $response, string $operation): self
    {
        $status = $response->status();
        $body = $response->json('message') ?? $response->body();

        return new self("WHMCS {$operation} HTTP {$status}: {$body}");
    }
}
