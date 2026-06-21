<?php

declare(strict_types=1);

namespace App\Modules\Infrastructure\Services;

use App\Modules\Infrastructure\Exceptions\ProxmoxWriteForbiddenException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ProxmoxClient
{
    /**
     * @return array<string, mixed>
     */
    public function getVmStatus(string $node, int $vmid): array
    {
        $path = "/api2/json/nodes/{$node}/qemu/{$vmid}/status/current";

        return $this->get($path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listClusterResources(): array
    {
        /** @var array{data?: list<array<string, mixed>>} $payload */
        $payload = $this->get('/api2/json/cluster/resources');

        return $payload['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        if (! str_starts_with($path, '/api2/json/')) {
            throw ProxmoxWriteForbiddenException::forMethod('INVALID_PATH');
        }

        if ($this->containsWriteSegment($path)) {
            throw ProxmoxWriteForbiddenException::forMethod('WRITE_PATH');
        }

        $response = $this->request()->get($this->baseUrl().$path);

        return $this->decodeResponse($response, $path);
    }

    private function containsWriteSegment(string $path): bool
    {
        $forbidden = ['POST', 'PUT', 'DELETE', 'create', 'destroy', 'start', 'stop', 'shutdown', 'reboot'];

        foreach ($forbidden as $segment) {
            if (stripos($path, $segment) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response, string $path): array
    {
        if (! $response->successful()) {
            throw new RuntimeException("Proxmox GET [{$path}] failed with HTTP {$response->status()}");
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException("Proxmox GET [{$path}] returned invalid JSON");
        }

        return $payload;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()->withHeaders([
            'Authorization' => 'PVEAPIToken='.$this->tokenId().'='.$this->tokenSecret(),
        ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('proxmox.url'), '/');
    }

    private function tokenId(): string
    {
        return (string) config('proxmox.token_id');
    }

    private function tokenSecret(): string
    {
        return (string) config('proxmox.token_secret');
    }
}
