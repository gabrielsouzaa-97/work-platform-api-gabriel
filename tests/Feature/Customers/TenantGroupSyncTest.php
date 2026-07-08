<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\TenantGroup;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Dto\TenantGroupSyncReport;
use App\Modules\Customers\Services\TenantGroupSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

function groupSyncCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function groupSyncCustomer(ClusterServer $cluster, string $slug): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function groupSyncSeedGroup(string $customerSlug, string $name, array $attrs = []): TenantGroup
{
    return TenantGroup::create(array_merge([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customerSlug,
        'name' => $name,
        'origin' => 'api',
        'created_at' => now()->subHours(1),
        'updated_at' => now()->subHours(1),
    ], $attrs));
}

function bindSyncGroupListViaSsh(Customer $customer, array $upstreamNames): void
{
    $parsed = ['groups' => $upstreamNames];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(function (
            ClusterServer $clusterArg,
            string $cmd,
            array $args,
            ?string $stdin,
            int $timeout,
        ) use ($customer): bool {
            return $cmd === 'nextcloud-manage'
                && ($args[0] ?? null) === $customer->slug
                && in_array('group:list', $args, true)
                && in_array('--output=json', $args, true)
                && $timeout === 30;
        })
        ->andReturn(new SshResponse(
            stdout: json_encode($parsed),
            stderr: '',
            exitCode: 0,
            parsedJson: $parsed,
        ));
    app()->instance(SshClientInterface::class, $ssh);
}

it('sync insere grupo ausente na projeção com origin sync', function (): void {
    $cluster = groupSyncCluster();
    $customer = groupSyncCustomer($cluster, 'grp-sync-insert');

    groupSyncSeedGroup($customer->slug, 'editors');
    groupSyncSeedGroup($customer->slug, 'staff');

    bindSyncGroupListViaSsh($customer, ['editors', 'staff', 'financeiro']);

    $report = app(TenantGroupSyncService::class)->sync($customer);

    expect($report)->toBeInstanceOf(TenantGroupSyncReport::class)
        ->and($report->inserted)->toBe(1)
        ->and($report->updated)->toBe(2)
        ->and($report->deleted)->toBe(0);

    $row = TenantGroup::where('customer_slug', $customer->slug)->where('name', 'financeiro')->first();
    expect($row)->not->toBeNull()
        ->and($row->origin)->toBe('sync');
});

it('sync remove grupo ausente no NC quando row local é antiga', function (): void {
    $cluster = groupSyncCluster();
    $customer = groupSyncCustomer($cluster, 'grp-sync-delete');

    groupSyncSeedGroup($customer->slug, 'editors');
    groupSyncSeedGroup($customer->slug, 'stale', [
        'origin' => 'api',
        'created_at' => now()->subHours(2),
    ]);

    bindSyncGroupListViaSsh($customer, ['editors']);

    $report = app(TenantGroupSyncService::class)->sync($customer);

    expect($report->deleted)->toBe(1)
        ->and(TenantGroup::where('customer_slug', $customer->slug)->where('name', 'stale')->exists())
        ->toBeFalse();
});

it('sync registra drift manual_creation para grupo no NC sem row local', function (): void {
    $cluster = groupSyncCluster();
    $customer = groupSyncCustomer($cluster, 'grp-sync-drift');

    bindSyncGroupListViaSsh($customer, ['orphan']);

    app(TenantGroupSyncService::class)->sync($customer);

    $drift = AuditLog::where('action', 'tenant_group_drift')
        ->where('resource_id', $customer->slug)
        ->first();

    expect($drift)->not->toBeNull()
        ->and($drift->payload['name'])->toBe('orphan')
        ->and($drift->payload['kind'])->toBe('manual_creation')
        ->and($drift->payload['customer_slug'])->toBe($customer->slug);
});

it('sync exclui grupo admin do upstream', function (): void {
    $cluster = groupSyncCluster();
    $customer = groupSyncCustomer($cluster, 'grp-sync-admin');

    bindSyncGroupListViaSsh($customer, ['admin', 'staff']);

    app(TenantGroupSyncService::class)->sync($customer);

    expect(TenantGroup::where('customer_slug', $customer->slug)->pluck('name')->all())
        ->toBe(['staff']);
});

it('sync incrementa updated em cenário misto insert e refresh de synced_at (CQ-F23-002)', function (): void {
    $cluster = groupSyncCluster();
    $customer = groupSyncCustomer($cluster, 'grp-sync-mixed');

    groupSyncSeedGroup($customer->slug, 'editors', ['synced_at' => null]);

    bindSyncGroupListViaSsh($customer, ['editors', 'financeiro']);

    $report = app(TenantGroupSyncService::class)->sync($customer);

    expect($report->inserted)->toBe(1)
        ->and($report->updated)->toBeGreaterThanOrEqual(1)
        ->and($report->deleted)->toBe(0);
});

it('sync incrementa updated quando row existente recebe refresh de synced_at (CQ-N46-007)', function (): void {
    $cluster = groupSyncCluster();
    $customer = groupSyncCustomer($cluster, 'grp-sync-updated');

    groupSyncSeedGroup($customer->slug, 'editors', ['synced_at' => null]);

    bindSyncGroupListViaSsh($customer, ['editors']);

    $report = app(TenantGroupSyncService::class)->sync($customer);

    expect($report->updated)->toBe(1)
        ->and($report->inserted)->toBe(0)
        ->and($report->deleted)->toBe(0);

    $row = TenantGroup::where('customer_slug', $customer->slug)->where('name', 'editors')->first();
    expect($row)->not->toBeNull()
        ->and($row->synced_at)->not->toBeNull();
});

it('tenant-groups:sync retorna FAILURE quando SSH falha em um customer (CQ-N46-008)', function (): void {
    $cluster = groupSyncCluster();
    $customerA = groupSyncCustomer($cluster, 'grp-sync-fail-a');
    $customerB = groupSyncCustomer($cluster, 'grp-sync-fail-b');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn (ClusterServer $c, string $cmd, array $args) => ($args[0] ?? null) === $customerA->slug)
        ->andThrow(new SshConnectionException('Connection refused'));
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn (ClusterServer $c, string $cmd, array $args) => ($args[0] ?? null) === $customerB->slug)
        ->andReturn(new SshResponse(
            stdout: json_encode(['groups' => ['team']]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['groups' => ['team']],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $exitCode = Artisan::call('tenant-groups:sync');

    expect($exitCode)->toBe(1)
        ->and(TenantGroup::where('customer_slug', $customerB->slug)->where('name', 'team')->exists())
        ->toBeTrue();
});

it('sync preserva row recém-criada ausente no NC por menos de 5 minutos', function (): void {
    Carbon::setTestNow('2026-07-08 12:00:00');

    $cluster = groupSyncCluster();
    $customer = groupSyncCustomer($cluster, 'grp-sync-grace');

    groupSyncSeedGroup($customer->slug, 'transit', [
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    bindSyncGroupListViaSsh($customer, []);

    $report = app(TenantGroupSyncService::class)->sync($customer);

    expect($report->deleted)->toBe(0)
        ->and(TenantGroup::where('customer_slug', $customer->slug)->where('name', 'transit')->exists())
        ->toBeTrue();

    Carbon::setTestNow();
});
