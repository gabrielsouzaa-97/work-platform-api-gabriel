<?php

declare(strict_types=1);

namespace App\Modules\Dns\Services;

use App\Modules\Dns\Exceptions\PdnsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PdnsClient
{
    private const int TIMEOUT_SECONDS = 30;

    private const int MAX_ATTEMPTS = 2;

    /**
     * @return array<string, mixed>
     */
    public function createZone(string $zone): array
    {
        $response = $this->send(
            fn (): Response => $this->request()->post($this->zonesUrl(), [
                'name' => $this->normalizeZone($zone),
                'kind' => 'Native',
                'nameservers' => [],
            ]),
        );

        if (! $response->successful()) {
            throw PdnsException::fromResponse($response, 'createZone');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function getZone(string $zone): array
    {
        $response = $this->send(
            fn (): Response => $this->request()->get($this->zoneUrl($zone)),
        );

        if (! $response->successful()) {
            throw PdnsException::fromResponse($response, 'getZone');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    public function upsertRecord(
        string $zone,
        string $name,
        string $type,
        string $content,
        int $ttl = 300,
    ): void {
        $response = $this->send(
            fn (): Response => $this->request()->patch($this->zoneUrl($zone), [
                'rrsets' => [[
                    'name' => $this->normalizeRecordName($name),
                    'type' => $type,
                    'ttl' => $ttl,
                    'changetype' => 'REPLACE',
                    'records' => [['content' => $content, 'disabled' => false]],
                ]],
            ]),
        );

        if (! $response->successful()) {
            throw PdnsException::fromResponse($response, 'upsertRecord');
        }
    }

    private function send(callable $callback): Response
    {
        $response = $callback();

        if ($response->status() >= 500 && self::MAX_ATTEMPTS > 1) {
            return $callback();
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders(['X-API-Key' => $this->apiKey()])
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS);
    }

    private function zonesUrl(): string
    {
        return rtrim($this->apiUrl(), '/').'/api/v1/servers/localhost/zones';
    }

    private function zoneUrl(string $zone): string
    {
        return $this->zonesUrl().'/'.$this->normalizeZone($zone);
    }

    private function normalizeZone(string $zone): string
    {
        return str_ends_with($zone, '.') ? $zone : "{$zone}.";
    }

    private function normalizeRecordName(string $name): string
    {
        return str_ends_with($name, '.') ? $name : "{$name}.";
    }

    private function apiUrl(): string
    {
        return (string) config('services.pdns.api_url');
    }

    private function apiKey(): string
    {
        return (string) config('services.pdns.api_key');
    }
}
