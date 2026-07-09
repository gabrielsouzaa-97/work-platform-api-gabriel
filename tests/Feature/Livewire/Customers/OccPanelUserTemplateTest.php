<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\OccPanel;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\TenantGroup;
use App\Models\TenantUser;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

function occTemplateCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function occTemplateCustomer(ClusterServer $cluster): Customer
{
    return Customer::create([
        'slug' => 'occ-tpl-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'occ-tpl.example.com',
        'status' => 'active',
    ]);
}

function occTemplateOperator(): Operator
{
    return Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
}

function seedOccUserTemplate(string $slug, array $overrides = []): void
{
    $row = array_merge([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'description' => null,
        'default_quota' => '12 GB',
        'groups' => json_encode(['supervisors', 'staff']),
        'permissions' => json_encode([
            'schema_version' => 1,
            'users' => ['hire' => true, 'block' => false, 'activate' => false],
            'apps' => ['install_from_store' => false, 'create_integration' => false],
            'audit' => ['read' => false],
        ]),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);

    if (isset($row['groups']) && is_array($row['groups'])) {
        $row['groups'] = json_encode($row['groups']);
    }

    DB::table('user_templates')->insert($row);
}

function seedOccTemplateTenantGroups(Customer $customer, array $names): void
{
    foreach ($names as $name) {
        TenantGroup::create([
            'id' => Str::uuid()->toString(),
            'customer_slug' => $customer->slug,
            'name' => $name,
            'origin' => 'api',
        ]);
    }
}

function bindOccTemplateSsh(string $jobId, ?callable $assertStdin = null): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($clusterArg, $cmd, $args, $stdin) use ($assertStdin): bool {
            if ($assertStdin === null) {
                return true;
            }

            return $assertStdin($stdin);
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);
}

it('createUser with template and empty userGroupSelection clears template groups in stdin (CQ-F17-002)', function (): void {
    $cluster = occTemplateCluster();
    $customer = occTemplateCustomer($cluster);
    $operator = occTemplateOperator();
    seedOccUserTemplate('supervisor');
    $jobId = Str::uuid()->toString();

    bindOccTemplateSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && array_key_exists('groups', $decoded)
            && $decoded['groups'] === [];
    });

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'cleared')
        ->set('userPasswordPlain', 'Secret123!')
        ->set('userTemplateSlug', 'supervisor')
        ->set('userGroupSelection', [])
        ->call('createUser')
        ->assertSet('successMessage', "Usuário enfileirado — job {$jobId}.");
});

it('OccPanel users tab lists active user templates for selection', function (): void {
    $cluster = occTemplateCluster();
    $customer = occTemplateCustomer($cluster);
    $operator = occTemplateOperator();
    seedOccUserTemplate('supervisor');
    seedOccUserTemplate('collaborator', ['groups' => ['users']]);
    seedOccUserTemplate('retired', ['status' => 'inactive']);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->call('setTab', 'users')
        ->assertSet('userTemplateSlug', '')
        ->assertSee('supervisor')
        ->assertSee('collaborator')
        ->assertDontSee('retired');
});

it('createUser with selected template merges groups into upstream stdin', function (): void {
    $cluster = occTemplateCluster();
    $customer = occTemplateCustomer($cluster);
    $operator = occTemplateOperator();
    seedOccUserTemplate('supervisor');
    seedOccTemplateTenantGroups($customer, ['supervisors', 'staff']);
    $jobId = Str::uuid()->toString();

    bindOccTemplateSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['groups'] ?? null) === ['supervisors', 'staff'];
    });

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userEmail', 'john@acme.com')
        ->set('userPasswordPlain', 'Secret123!')
        ->set('userTemplateSlug', 'supervisor')
        ->call('createUser')
        ->assertSet('successMessage', "Usuário enfileirado — job {$jobId}.");
});

it('createUser explicit userGroupSelection override selected template groups', function (): void {
    $cluster = occTemplateCluster();
    $customer = occTemplateCustomer($cluster);
    $operator = occTemplateOperator();
    seedOccUserTemplate('supervisor');
    TenantGroup::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'name' => 'financeiro',
        'origin' => 'api',
    ]);
    $jobId = Str::uuid()->toString();

    bindOccTemplateSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['groups'] ?? null) === ['financeiro'];
    });

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'alice')
        ->set('userPasswordPlain', 'Secret123!')
        ->set('userTemplateSlug', 'supervisor')
        ->set('userGroupSelection', ['financeiro'])
        ->call('createUser')
        ->assertSet('successMessage', "Usuário enfileirado — job {$jobId}.");
});

it('createUser with selected template merges default_quota into upstream stdin', function (): void {
    $cluster = occTemplateCluster();
    $customer = occTemplateCustomer($cluster);
    $operator = occTemplateOperator();
    seedOccUserTemplate('collaborator', ['default_quota' => '12 GB', 'groups' => []]);
    $jobId = Str::uuid()->toString();

    bindOccTemplateSsh($jobId, function (?string $stdin): bool {
        $decoded = json_decode($stdin ?? '', true);

        return is_array($decoded)
            && ($decoded['quota'] ?? null) === '12 GB';
    });

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'bob')
        ->set('userPasswordPlain', 'Secret123!')
        ->set('userTemplateSlug', 'collaborator')
        ->call('createUser')
        ->assertSet('successMessage', "Usuário enfileirado — job {$jobId}.");
});

it('createUser stores user_template_slug in job payload_sanitized with origin panel', function (): void {
    $cluster = occTemplateCluster();
    $customer = occTemplateCustomer($cluster);
    $operator = occTemplateOperator();
    seedOccUserTemplate('supervisor');
    seedOccTemplateTenantGroups($customer, ['supervisors', 'staff']);
    $jobId = Str::uuid()->toString();

    bindOccTemplateSsh($jobId);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'carol')
        ->set('userPasswordPlain', 'Secret123!')
        ->set('userTemplateSlug', 'supervisor')
        ->call('createUser');

    $job = Job::query()->where('job_id', $jobId)->first();
    expect($job)->not->toBeNull()
        ->and($job->payload_sanitized['user_template_slug'] ?? null)->toBe('supervisor')
        ->and($job->payload_sanitized['origin'] ?? null)->toBe('panel');
});

it('createUser rejects template groups missing from tenant_groups before SSH', function (): void {
    $cluster = occTemplateCluster();
    $customer = occTemplateCustomer($cluster);
    $operator = occTemplateOperator();
    seedOccUserTemplate('supervisor');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'partial-tpl')
        ->set('userPasswordPlain', 'Secret123!')
        ->set('userTemplateSlug', 'supervisor')
        ->call('createUser')
        ->assertSet('pendingUserCreateJobId', '')
        ->assertSet('errorMessage', fn (string $message): bool => str_contains($message, 'supervisors'))
        ->assertSet('userPasswordPlain', '');
});

it('createUser rejects unknown userTemplateSlug before SSH', function (): void {
    $cluster = occTemplateCluster();
    $customer = occTemplateCustomer($cluster);
    $operator = occTemplateOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'dave')
        ->set('userPasswordPlain', 'Secret123!')
        ->set('userTemplateSlug', 'missing-template')
        ->call('createUser')
        ->assertHasErrors(['userTemplateSlug'])
        ->assertSet('userPasswordPlain', '');
});

it('createUser shows plan limit message when max_users reached', function (): void {
    $cluster = occTemplateCluster();
    $planSlug = 'occ-limit-'.substr(uniqid(), -6);
    Plan::create([
        'slug' => $planSlug,
        'name' => 'Limited',
        'default_quota' => '5 GB',
        'max_users' => 1,
        'is_default' => false,
        'status' => 'active',
    ]);
    $customer = Customer::create([
        'slug' => 'occ-limit-cust-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'occ-limit.example.com',
        'status' => 'active',
        'plan_slug' => $planSlug,
    ]);
    TenantUser::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'username' => 'alice',
        'origin' => 'api',
    ]);
    $operator = occTemplateOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'bob')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertSet('errorMessage', 'Limite do plano excedido para criação de usuários.')
        ->assertSet('userPasswordPlain', '');
});
