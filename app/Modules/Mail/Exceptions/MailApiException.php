<?php

declare(strict_types=1);

namespace App\Modules\Mail\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

final class MailApiException extends RuntimeException
{
    public static function fromResponse(Response $response, string $operation): self
    {
        $status = $response->status();
        $body = $response->json('error') ?? $response->body();

        return new self("Mail API {$operation} failed with HTTP {$status}: {$body}");
    }
}
