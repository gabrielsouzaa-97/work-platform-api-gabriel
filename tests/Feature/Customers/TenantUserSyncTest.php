<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\TenantUser;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Dto\TenantUserSyncReport;
use App\Modules\Customers\Services\TenantUserSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

function syncCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function syncCustomer(ClusterServer $cluster, string $slug): Customer
{
    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function syncSeedUser(string $customerSlug, string $username, array $attrs = []): TenantUser
{
    return TenantUser::create(array_merge([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customerSlug,
        'username' => $username,
        'email' => "{$username}@example.com",
        'quota' => '5 GB',
        'groups' => ['users'],
        'origin' => 'api',
        'created_at' => now()->subHours(1),
        'updated_at' => now()->subHours(1),
    ], $attrs));
}

function syncUpstreamUsers(array $rows): array
{
    return array_map(
        fn (array $row): array => [
            'username' => $row['username'],
            'email' => $row['email'] ?? "{$row['username']}@example.com",
            'quota' => $row['quota'] ?? '5 GB',
            'groups' => $row['groups'] ?? ['users'],
        ],
        $rows,
    );
}

function bindSyncUserListViaSsh(Customer $customer, array $upstreamRows): void
{
    $parsed = syncUpstreamUsers($upstreamRows);

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
                && in_array('user:list', $args, true)
                && in_array('--json', $args, true)
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

it('sync insere usuário ausente na projeção com origin sync', function (): void {
    $cluster = syncCluster();
    $customer = syncCustomer($cluster, 'sync-insert');

    syncSeedUser($customer->slug, 'alice');
    syncSeedUser($customer->slug, 'bob');

    bindSyncUserListViaSsh($customer, [
        ['username' => 'alice'],
        ['username' => 'bob'],
        ['username' => 'carol', 'email' => 'carol@example.com'],
    ]);

    $report = app(TenantUserSyncService::class)->sync($customer);

    expect($report)->toBeInstanceOf(TenantUserSyncReport::class)
        ->and($report->inserted)->toBe(1)
        ->and($report->updated)->toBe(0)
        ->and($report->deleted)->toBe(0);

    $carol = TenantUser::where('customer_slug', $customer->slug)->where('username', 'carol')->first();
    expect($carol)->not->toBeNull()
        ->and($carol->origin)->toBe('sync');
});

it('sync remove usuário ausente no NC quando row local é antiga', function (): void {
    $cluster = syncCluster();
    $customer = syncCustomer($cluster, 'sync-delete');

    syncSeedUser($customer->slug, 'alice');
    syncSeedUser($customer->slug, 'stale', [
        'origin' => 'api',
        'created_at' => now()->subHours(2),
    ]);

    bindSyncUserListViaSsh($customer, [
        ['username' => 'alice'],
    ]);

    $report = app(TenantUserSyncService::class)->sync($customer);

    expect($report->deleted)->toBe(1)
        ->and(TenantUser::where('customer_slug', $customer->slug)->where('username', 'stale')->exists())
        ->toBeFalse();
});

it('sync registra drift manual_creation para usuário no NC sem row local', function (): void {
    $cluster = syncCluster();
    $customer = syncCustomer($cluster, 'sync-drift-manual');

    bindSyncUserListViaSsh($customer, [
        ['username' => 'orphan', 'email' => 'orphan@example.com'],
    ]);

    app(TenantUserSyncService::class)->sync($customer);

    $drift = AuditLog::where('action', 'tenant_user_drift')
        ->where('resource_id', $customer->slug)
        ->first();

    expect($drift)->not->toBeNull()
        ->and($drift->payload['username'])->toBe('orphan')
        ->and($drift->payload['kind'])->toBe('manual_creation')
        ->and($drift->payload['customer_slug'])->toBe($customer->slug);
});

it('sync registra drift admin_group_member para usuário não-admin em grupo admin', function (): void {
    $cluster = syncCluster();
    $customer = syncCustomer($cluster, 'sync-drift-admin-grp');

    bindSyncUserListViaSsh($customer, [
        ['username' => 'privuser', 'groups' => ['admin', 'users']],
    ]);

    app(TenantUserSyncService::class)->sync($customer);

    $drift = AuditLog::where('action', 'tenant_user_drift')
        ->where('resource_id', $customer->slug)
        ->first();

    expect($drift)->not->toBeNull()
        ->and($drift->payload['username'])->toBe('privuser')
        ->and($drift->payload['kind'])->toBe('admin_group_member');
});

it('sync registra drift admin_group_member para usuário não-admin em grupo subadmin', function (): void {
    $cluster = syncCluster();
    $customer = syncCustomer($cluster, 'sync-drift-subadmin-grp');

    bindSyncUserListViaSsh($customer, [
        ['username' => 'privuser', 'groups' => ['subadmin']],
    ]);

    app(TenantUserSyncService::class)->sync($customer);

    $drift = AuditLog::where('action', 'tenant_user_drift')
        ->where('resource_id', $customer->slug)
        ->first();

    expect($drift)->not->toBeNull()
        ->and($drift->payload['username'])->toBe('privuser')
        ->and($drift->payload['kind'])->toBe('admin_group_member');
});

it('sync não registra drift para admin origin provision', function (): void {
    $cluster = syncCluster();
    $customer = syncCustomer($cluster, 'sync-no-drift-admin');

    syncSeedUser($customer->slug, 'admin', [
        'origin' => 'provision',
        'groups' => ['admin'],
    ]);

    bindSyncUserListViaSsh($customer, [
        ['username' => 'admin', 'groups' => ['admin']],
    ]);

    $report = app(TenantUserSyncService::class)->sync($customer);

    expect($report->driftDetected)->toBe(0)
        ->and(AuditLog::where('action', 'tenant_user_drift')->where('resource_id', $customer->slug)->exists())
        ->toBeFalse();
});

it('sync preserva admin origin provision ausente do user:list e registra missing_platform_admin', function (): void {
    $cluster = syncCluster();
    $customer = syncCustomer($cluster, 'sync-missing-admin');

    syncSeedUser($customer->slug, 'admin', [
        'origin' => 'provision',
        'groups' => ['admin'],
        'created_at' => now()->subHours(2),
    ]);

    bindSyncUserListViaSsh($customer, []);

    $report = app(TenantUserSyncService::class)->sync($customer);

    expect($report->deleted)->toBe(0)
        ->and($report->driftDetected)->toBe(1)
        ->and(TenantUser::where('customer_slug', $customer->slug)->where('username', 'admin')->exists())
        ->toBeTrue();

    $drift = AuditLog::where('action', 'tenant_user_drift')
        ->where('resource_id', $customer->slug)
        ->first();

    expect($drift)->not->toBeNull()
        ->and($drift->payload['username'])->toBe('admin')
        ->and($drift->payload['kind'])->toBe('missing_platform_admin');
});

it('tenant-users:sync continua batch quando SSH falha em um customer', function (): void {
    $cluster = syncCluster();
    $customerA = syncCustomer($cluster, 'sync-fail-a');
    $customerB = syncCustomer($cluster, 'sync-fail-b');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn (ClusterServer $c, string $cmd, array $args) => ($args[0] ?? null) === $customerA->slug)
        ->andThrow(new SshConnectionException('Connection refused'));
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn (ClusterServer $c, string $cmd, array $args) => ($args[0] ?? null) === $customerB->slug)
        ->andReturn(new SshResponse(
            stdout: json_encode(syncUpstreamUsers([['username' => 'bob']])),
            stderr: '',
            exitCode: 0,
            parsedJson: syncUpstreamUsers([['username' => 'bob']]),
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $exitCode = Artisan::call('tenant-users:sync');

    expect($exitCode)->toBe(0)
        ->and(TenantUser::where('customer_slug', $customerB->slug)->where('username', 'bob')->exists())
        ->toBeTrue();
});

it('sync preserva row recém-criada ausente no NC por menos de 5 minutos', function (): void {
    Carbon::setTestNow('2026-07-05 12:00:00');

    $cluster = syncCluster();
    $customer = syncCustomer($cluster, 'sync-grace');

    syncSeedUser($customer->slug, 'transit', [
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    bindSyncUserListViaSsh($customer, []);

    $report = app(TenantUserSyncService::class)->sync($customer);

    expect($report->deleted)->toBe(0)
        ->and(TenantUser::where('customer_slug', $customer->slug)->where('username', 'transit')->exists())
        ->toBeTrue();

    Carbon::setTestNow();
});
