<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Services\CustomerSyncService;

function makeSyncCluster(string $name = 'sync-cluster'): ClusterServer
{
    return ClusterServer::factory()->create(['name' => $name, 'status' => 'active']);
}

it('cron sync insere customer upstream não presente local', function () {
    $cluster = makeSyncCluster();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('run')
        ->once()
        ->andReturn(new SshResponse(
            stdout: "acme-new  acme-new.example.com  active\n",
            stderr: '',
            exitCode: 0,
            parsedJson: null,
        ));

    $svc = new CustomerSyncService($sshMock);
    $report = $svc->sync($cluster);

    expect($report->inserted)->toBe(1)
        ->and($report->updated)->toBe(0)
        ->and($report->deleted)->toBe(0);

    expect(Customer::find('acme-new'))->not->toBeNull();
    expect(AuditLog::where('action', 'customer_sync_inserted')->where('resource_id', 'acme-new')->exists())->toBeTrue();
});

it('cron sync soft-deleta customer presente local mas não no upstream', function () {
    $cluster = makeSyncCluster('sync-cluster-2');

    Customer::create([
        'slug' => 'orphan-co',
        'cluster_server_id' => $cluster->id,
        'domain' => 'orphan.example.com',
        'status' => 'active',
    ]);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('run')
        ->once()
        ->andReturn(new SshResponse(stdout: '', stderr: '', exitCode: 0, parsedJson: null));

    $svc = new CustomerSyncService($sshMock);
    $report = $svc->sync($cluster);

    expect($report->deleted)->toBe(1);
    expect(Customer::withTrashed()->find('orphan-co')->deleted_at)->not->toBeNull();
    expect(AuditLog::where('action', 'customer_sync_removed')->where('resource_id', 'orphan-co')->exists())->toBeTrue();
});

it('cluster offline → SSH exception não interrompe processo', function () {
    $cluster = makeSyncCluster('sync-cluster-3');
    $offlineCluster = ClusterServer::factory()->create(['name' => 'offline', 'status' => 'active']);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('run')
        ->andThrow(new SshConnectionException('Connection refused'));

    $svc = new CustomerSyncService($sshMock);

    expect(fn () => $svc->sync($cluster))->toThrow(SshConnectionException::class);
});

it('filtros combinados status + cluster retornam subset correto', function () {
    $cluster = makeSyncCluster('sync-cluster-4');

    $admin = Operator::factory()->create(['role' => 'admin', 'status' => 'active']);

    Customer::create(['slug' => 'acme-active', 'cluster_server_id' => $cluster->id, 'domain' => 'a.example.com', 'status' => 'active']);
    Customer::create(['slug' => 'acme-removed', 'cluster_server_id' => $cluster->id, 'domain' => 'b.example.com', 'status' => 'removed']);

    $response = $this->actingAs($admin)->get('/customers?status=active&cluster='.$cluster->id);
    $response->assertOk();
});
