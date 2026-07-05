<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\Show;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

function makeShowCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeShowCustomer(ClusterServer $cluster, string $status = 'active'): Customer
{
    return Customer::create([
        'slug' => 'show-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'show-test.example.com',
        'status' => $status,
    ]);
}

function makeShowOperator(string $role = 'operador'): Operator
{
    return Operator::factory()->create(['role' => $role, 'status' => 'active']);
}

function makeShowJob(Customer $customer, ClusterServer $cluster, array $overrides = []): Job
{
    return Job::create(array_merge([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'nextcloud-manage '.$customer->slug.' _ provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ], $overrides));
}

function bindSshJobLogs(array $lines): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->andReturn(new SshResponse(
        stdout: json_encode($lines),
        stderr: '',
        exitCode: 0,
        parsedJson: $lines,
    ));
    app()->instance(SshClientInterface::class, $ssh);
}

function bindSshJobLogsThrows(): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->andThrow(new SshConnectionException('connection refused'));
    app()->instance(SshClientInterface::class, $ssh);
}

it('customer provisioning renderiza wire:poll.5s para refreshProgress', function (): void {
    $cluster = makeShowCluster();
    $customer = makeShowCustomer($cluster, 'provisioning');
    $operator = makeShowOperator();

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertSeeHtml('wire:poll.5s="refreshProgress"');
});

it('customer active nao renderiza wire:poll', function (): void {
    $cluster = makeShowCluster();
    $customer = makeShowCustomer($cluster, 'active');
    $operator = makeShowOperator();

    $html = Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->html();

    expect($html)->not->toContain('wire:poll');
});

it('job running exibe tail lines via JobLogFetcher mockado', function (): void {
    Cache::flush();

    $cluster = makeShowCluster();
    $customer = makeShowCustomer($cluster, 'provisioning');
    $job = makeShowJob($customer, $cluster);
    $operator = makeShowOperator();

    bindSshJobLogs([
        'line one',
        'line two',
        'line three',
        'line four',
        'line five',
        'line six',
    ]);

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertSee('line two')
        ->assertSee('line six')
        ->assertDontSee('line one');
});

it('JobLogFetcher SSH fail exibe pagina sem tail e sem erro', function (): void {
    Cache::flush();

    $cluster = makeShowCluster();
    $customer = makeShowCustomer($cluster, 'provisioning');
    makeShowJob($customer, $cluster);
    $operator = makeShowOperator();

    bindSshJobLogsThrows();

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertStatus(200)
        ->assertDontSee('Log em execução');
});

it('cada job row linka para queue.show', function (): void {
    $cluster = makeShowCluster();
    $customer = makeShowCustomer($cluster, 'active');
    $job = makeShowJob($customer, $cluster, ['state' => 'success', 'finished_at' => now()]);
    $operator = makeShowOperator();

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertSeeHtml(route('queue.show', $job->job_id));
});

it('refreshProgress atualiza status do customer apos mudanca no banco', function (): void {
    $cluster = makeShowCluster();
    $customer = makeShowCustomer($cluster, 'provisioning');
    $operator = makeShowOperator();

    $component = Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertSet('customer.status', 'provisioning');

    $customer->update(['status' => 'active']);

    $component
        ->call('refreshProgress')
        ->assertSet('customer.status', 'active');
});

it('modal de remocao permanece disponivel para customer active', function (): void {
    $cluster = makeShowCluster();
    $customer = makeShowCustomer($cluster, 'active');
    $operator = makeShowOperator();

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertSee('Remover')
        ->call('$set', 'showRemoveModal', true)
        ->assertSee('Remover customer')
        ->assertSee($customer->slug);
});

it('shouldPoll retorna true para provisioning_finishing e removing', function (string $status): void {
    $cluster = makeShowCluster();
    $customer = makeShowCustomer($cluster, $status);
    $operator = makeShowOperator();

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertSet('customer.status', $status)
        ->assertSeeHtml('wire:poll.5s="refreshProgress"');
})->with(['provisioning_finishing', 'removing']);
