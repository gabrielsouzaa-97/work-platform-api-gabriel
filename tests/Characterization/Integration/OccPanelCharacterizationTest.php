<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\OccPanel;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('run');
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);
});

function characterizationOccPanelCustomer(): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    return Customer::create([
        'slug' => 'char-occ-panel-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-occ-panel.example.com',
        'status' => 'active',
    ]);
}

it('characterizes submitQuota occ-exec argv via OccPassthroughService', function (): void {
    $customer = characterizationOccPanelCustomer();
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $payload = ['exit_code' => 0];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args) use ($customer): bool {
            return $cmd === 'nextcloud-manage'
                && $args === [$customer->slug, 'occ-exec', 'user:setting', 'alice', 'files', 'quota', '5GB', '--json'];
        })
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('quotaUsername', 'alice')
        ->set('quotaValue', '5 GB')
        ->set('quotaScope', 'user')
        ->call('submitQuota')
        ->assertSet('successMessage', 'Quota atualizada com sucesso.');
});

it('characterizes submitApp enable invokes occ-exec app:enable', function (): void {
    $customer = characterizationOccPanelCustomer();
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $payload = ['exit_code' => 0];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && $args === [$customer->slug, 'occ-exec', 'app:enable', 'calendar', '--json'])
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('appId', 'calendar')
        ->call('submitApp')
        ->assertSet('successMessage', "App 'calendar' habilitado via OCC.");
});

it('characterizes createUser dispatches async manage argv not sync occ-exec', function (): void {
    $customer = characterizationOccPanelCustomer();
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('user', $args, true)
            && in_array('create', $args, true))
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'newbie')
        ->set('userPasswordPlain', 'secret123')
        ->call('createUser')
        ->assertSet('successMessage', "Usuário enfileirado — job {$jobId}.");
});
