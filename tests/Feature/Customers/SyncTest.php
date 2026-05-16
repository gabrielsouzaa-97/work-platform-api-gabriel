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

/** Build an upstream JSON payload with the real schema_v1 format. */
function upstreamJson(array $instances = [], array $sharedServices = []): array
{
    return [
        'schema_version' => '1',
        'instances' => $instances,
        'shared_services' => $sharedServices,
    ];
}

function upstreamInstance(string $name, string $domain, string $status = 'running'): array
{
    return ['name' => $name, 'domain' => $domain, 'status' => $status];
}

function makeSyncSshMock(array $parsedJson, int $exitCode = 0): SshClientInterface
{
    $mock = Mockery::mock(SshClientInterface::class);
    $mock->shouldReceive('run')
        ->once()
        ->with(Mockery::any(), 'nextcloud-manage', ['list', '--json'], null, 30)
        ->andReturn(new SshResponse(
            stdout: json_encode($parsedJson),
            stderr: '',
            exitCode: $exitCode,
            parsedJson: $parsedJson,
        ));

    return $mock;
}

it('cron sync insere customer upstream não presente local', function () {
    $cluster = makeSyncCluster();

    $payload = upstreamJson([
        upstreamInstance('acme-new', 'acme-new.example.com'),
    ]);

    $svc = new CustomerSyncService(makeSyncSshMock($payload));
    $report = $svc->sync($cluster);

    expect($report->inserted)->toBe(1)
        ->and($report->updated)->toBe(0)
        ->and($report->deleted)->toBe(0);

    $customer = Customer::find('acme-new');
    expect($customer)->not->toBeNull();
    expect($customer->status)->toBe('active');
    expect(AuditLog::where('action', 'customer_sync_inserted')->where('resource_id', 'acme-new')->exists())->toBeTrue();
});

it('status running do upstream é traduzido para active local', function () {
    $cluster = makeSyncCluster('sync-cluster-status');

    $payload = upstreamJson([
        upstreamInstance('my-org', 'my-org.example.com', 'running'),
    ]);

    $svc = new CustomerSyncService(makeSyncSshMock($payload));
    $svc->sync($cluster);

    expect(Customer::find('my-org')->status)->toBe('active');
});

it('status stopped do upstream é traduzido para removed local', function () {
    $cluster = makeSyncCluster('sync-cluster-stopped');

    Customer::create([
        'slug' => 'paused-org',
        'cluster_server_id' => $cluster->id,
        'domain' => 'paused.example.com',
        'status' => 'active',
    ]);

    $payload = upstreamJson([
        upstreamInstance('paused-org', 'paused.example.com', 'stopped'),
    ]);

    $svc = new CustomerSyncService(makeSyncSshMock($payload));
    $report = $svc->sync($cluster);

    expect($report->updated)->toBe(1);
    expect(Customer::find('paused-org')->status)->toBe('removed');
});

it('shared_services são ignorados e não inserem customers', function () {
    $cluster = makeSyncCluster('sync-cluster-shared');

    $payload = upstreamJson(
        instances: [upstreamInstance('real-org', 'real-org.example.com')],
        sharedServices: [
            ['name' => 'shared-db', 'status' => 'running'],
            ['name' => 'shared-redis', 'status' => 'running'],
        ]
    );

    $svc = new CustomerSyncService(makeSyncSshMock($payload));
    $report = $svc->sync($cluster);

    expect($report->inserted)->toBe(1);
    expect(Customer::find('real-org'))->not->toBeNull();
    expect(Customer::find('shared-db'))->toBeNull();
    expect(Customer::find('shared-redis'))->toBeNull();
});

it('cron sync soft-deleta customer presente local mas não no upstream', function () {
    $cluster = makeSyncCluster('sync-cluster-2');

    Customer::create([
        'slug' => 'orphan-co',
        'cluster_server_id' => $cluster->id,
        'domain' => 'orphan.example.com',
        'status' => 'active',
    ]);

    $payload = upstreamJson([]);

    $svc = new CustomerSyncService(makeSyncSshMock($payload));
    $report = $svc->sync($cluster);

    expect($report->deleted)->toBe(1);
    expect(Customer::withTrashed()->find('orphan-co')->deleted_at)->not->toBeNull();
    expect(AuditLog::where('action', 'customer_sync_removed')->where('resource_id', 'orphan-co')->exists())->toBeTrue();
});

it('cluster offline → CustomerSyncService lança SshConnectionException', function () {
    $cluster = makeSyncCluster('sync-cluster-3');

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('run')->andThrow(new SshConnectionException('Connection refused'));

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

it('instâncias com name inválido no JSON são ignoradas', function () {
    $cluster = makeSyncCluster('sync-cluster-invalid');

    $payload = upstreamJson([
        upstreamInstance('valid-org', 'valid.example.com'),
        upstreamInstance('===', 'invalid.example.com'),
        upstreamInstance('', 'empty.example.com'),
        upstreamInstance('UPPER', 'upper.example.com'),
        ['domain' => 'no-name.example.com', 'status' => 'running'],
    ]);

    $svc = new CustomerSyncService(makeSyncSshMock($payload));
    $report = $svc->sync($cluster);

    expect($report->inserted)->toBe(1);
    expect(Customer::find('valid-org'))->not->toBeNull();
    expect(Customer::find('==='))->toBeNull();
    expect(Customer::find('UPPER'))->toBeNull();
});

it('name com dígitos e hífens é aceito pelo parser JSON', function () {
    $cluster = makeSyncCluster('sync-cluster-slug');

    $payload = upstreamJson([
        upstreamInstance('123-corp', '123-corp.example.com'),
        upstreamInstance('acme-corp-2', 'acme-corp-2.example.com'),
    ]);

    $svc = new CustomerSyncService(makeSyncSshMock($payload));
    $report = $svc->sync($cluster);

    expect($report->inserted)->toBe(2);
    expect(Customer::find('123-corp'))->not->toBeNull();
    expect(Customer::find('acme-corp-2'))->not->toBeNull();
});

it('resposta sem chave instances aborta sync sem alterar dados locais', function () {
    $cluster = makeSyncCluster('sync-cluster-noinstances');

    Customer::create([
        'slug' => 'existing-co',
        'cluster_server_id' => $cluster->id,
        'domain' => 'existing.example.com',
        'status' => 'active',
    ]);

    $payload = ['schema_version' => '1', 'unexpected_key' => []];

    $svc = new CustomerSyncService(makeSyncSshMock($payload));
    $report = $svc->sync($cluster);

    expect($report->inserted)->toBe(0)
        ->and($report->updated)->toBe(0)
        ->and($report->deleted)->toBe(0);

    expect(Customer::find('existing-co'))->not->toBeNull();
});

it('resposta não-JSON (parsedJson null) aborta sync sem alterar dados locais', function () {
    $cluster = makeSyncCluster('sync-cluster-badjson');

    Customer::create([
        'slug' => 'existing-co',
        'cluster_server_id' => $cluster->id,
        'domain' => 'existing.example.com',
        'status' => 'active',
    ]);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('run')
        ->once()
        ->andReturn(new SshResponse(
            stdout: 'this is not json',
            stderr: '',
            exitCode: 0,
            parsedJson: null,
        ));

    $svc = new CustomerSyncService($sshMock);
    $report = $svc->sync($cluster);

    expect($report->inserted)->toBe(0)
        ->and($report->updated)->toBe(0)
        ->and($report->deleted)->toBe(0);

    expect(Customer::find('existing-co'))->not->toBeNull();
});
