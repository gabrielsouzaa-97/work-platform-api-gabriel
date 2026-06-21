<?php

declare(strict_types=1);

use App\Modules\Infrastructure\Exceptions\ProxmoxWriteForbiddenException;
use App\Modules\Infrastructure\Services\ProxmoxClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'proxmox.url' => 'https://proxmox.test:8006',
        'proxmox.token_id' => 'PVEAuditor@pve!read',
        'proxmox.token_secret' => 'secret-token',
        'proxmox.cluster' => 'IDC-EVEO',
    ]);
});

function proxmoxClient(): ProxmoxClient
{
    return app(ProxmoxClient::class);
}

it('getVmStatus performs read-only GET for VM status', function (): void {
    Http::fake([
        'https://proxmox.test:8006/api2/json/nodes/pve1/qemu/101/status/current' => Http::response([
            'data' => ['status' => 'running', 'vmid' => 101],
        ], 200),
    ]);

    $result = proxmoxClient()->getVmStatus('pve1', 101);

    expect($result['data']['status'])->toBe('running');

    Http::assertSent(function ($request): bool {
        return $request->method() === 'GET'
            && $request->url() === 'https://proxmox.test:8006/api2/json/nodes/pve1/qemu/101/status/current'
            && $request->header('Authorization')[0] === 'PVEAPIToken=PVEAuditor@pve!read=secret-token';
    });
});

it('listClusterResources performs read-only GET for cluster inventory', function (): void {
    Http::fake([
        'https://proxmox.test:8006/api2/json/cluster/resources' => Http::response([
            'data' => [
                ['type' => 'node', 'node' => 'pve1'],
                ['type' => 'qemu', 'vmid' => 101, 'node' => 'pve1'],
            ],
        ], 200),
    ]);

    $resources = proxmoxClient()->listClusterResources();

    expect($resources)->toHaveCount(2)
        ->and($resources[0]['type'])->toBe('node');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/api2/json/cluster/resources'));
});

it('rejects write-like paths', function (): void {
    $reflection = new ReflectionClass(ProxmoxClient::class);
    $method = $reflection->getMethod('get');
    $method->setAccessible(true);

    expect(fn () => $method->invoke(proxmoxClient(), '/api2/json/nodes/pve1/qemu/101/status/start'))
        ->toThrow(ProxmoxWriteForbiddenException::class);
});

it('throws on HTTP failure', function (): void {
    Http::fake([
        'https://proxmox.test:8006/api2/json/cluster/resources' => Http::response('forbidden', 403),
    ]);

    proxmoxClient()->listClusterResources();
})->throws(RuntimeException::class);
