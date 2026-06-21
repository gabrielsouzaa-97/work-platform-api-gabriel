<?php

declare(strict_types=1);

namespace App\Modules\Mail\Services;

use App\Modules\Mail\Exceptions\MailApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class MailApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function createDomain(string $domain): array
    {
        $response = $this->request()
            ->post($this->endpoint('/v1/domains'), ['domain' => $domain]);

        if ($response->status() === 409) {
            return [];
        }

        if (! $response->successful()) {
            throw MailApiException::fromResponse($response, 'createDomain');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function createMailbox(string $domain, string $localPart, string $password): array
    {
        $response = $this->request()->post($this->endpoint('/v1/mailboxes'), [
            'domain' => $domain,
            'local_part' => $localPart,
            'password' => $password,
        ]);

        if ($response->status() === 409) {
            return [];
        }

        if (! $response->successful()) {
            throw MailApiException::fromResponse($response, 'createMailbox');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }

    public function isHealthy(): bool
    {
        $response = $this->request()->get($this->endpoint('/v1/health'));

        return $response->successful();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchDkim(string $domain): array
    {
        $response = $this->request()->get($this->endpoint("/v1/domains/{$domain}/dkim"));

        if (! $response->successful()) {
            throw MailApiException::fromResponse($response, 'fetchDkim');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->apiKey())
            ->acceptJson()
            ->asJson();
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('mail_api.base_url'), '/').$path;
    }

    private function apiKey(): string
    {
        return (string) config('mail_api.api_key');
    }
}
