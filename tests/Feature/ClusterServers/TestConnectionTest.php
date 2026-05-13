<?php

declare(strict_types=1);

use App\Http\Livewire\ClusterServers\Index;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use Livewire\Livewire;
use Mockery\MockInterface;

it('test connection bem-sucedido atualiza status para active e last_health_at', function () {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['status' => 'unreachable']);

    $this->mock(SshClientInterface::class, function (MockInterface $mock) use ($cluster) {
        $mock->shouldReceive('run')
            ->once()
            ->withArgs(fn ($c, $cmd, $args, $payload, $timeout) => $c->id === $cluster->id
                && $cmd === 'echo'
                && $args === ["healthcheck-{$cluster->id}"]
                && $timeout === 10
            )
            ->andReturn(new SshResponse(
                stdout: "healthcheck-{$cluster->id}\n",
                stderr: '',
                exitCode: 0,
            ));
    });

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('testConnection', $cluster->id)
        ->assertDispatched('toast', type: 'success');

    $cluster->refresh();
    expect($cluster->status)->toBe('active')
        ->and($cluster->last_health_at)->not->toBeNull();
});

it('test connection com SshConnectionException marca status unreachable', function () {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    $this->mock(SshClientInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('run')
            ->once()
            ->andThrow(new SshConnectionException('Connection refused'));
    });

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('testConnection', $cluster->id)
        ->assertDispatched('toast', type: 'error');

    $cluster->refresh();
    expect($cluster->status)->toBe('unreachable');
});

it('test connection com SshTimeoutException marca status unreachable com mensagem de timeout', function () {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    $this->mock(SshClientInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('run')
            ->once()
            ->andThrow(new SshTimeoutException('Timeout'));
    });

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('testConnection', $cluster->id)
        ->assertDispatched('toast', type: 'error', msg: 'Timeout ao conectar');

    $cluster->refresh();
    expect($cluster->status)->toBe('unreachable');
});

it('operador comum não pode chamar testConnection', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);
    $cluster = ClusterServer::factory()->create();

    $this->mock(SshClientInterface::class, fn (MockInterface $m) => $m->shouldNotReceive('run'));

    Livewire::actingAs($operador)
        ->test(Index::class)
        ->call('testConnection', $cluster->id)
        ->assertForbidden();
});
