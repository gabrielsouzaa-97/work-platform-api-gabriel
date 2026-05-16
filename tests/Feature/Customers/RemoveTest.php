<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\Show;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;
use Livewire\Livewire;

function makeRemovableCustomer(string $slug = 'acme-remove'): array
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => $slug.'.example.com',
        'status' => 'active',
    ]);

    return [$cluster, $customer];
}

function sshRemoveSuccess(string $jobId): SshResponse
{
    return new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    );
}

it('operador digita slug correto + backup ON → 202 + job criado + customer.status=removing + audit', function () {
    [$cluster, $customer] = makeRemovableCustomer('acme-rm-ok');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(sshRemoveSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->deleteJson('/api/customers/acme-rm-ok', [
        'confirm_slug' => 'acme-rm-ok',
        'backup_first' => true,
    ]);

    $response->assertStatus(202);
    $response->assertJsonPath('job_id', $jobId);

    $customer->refresh();
    expect($customer->status)->toBe('removing');
    expect(Job::find($jobId))->not->toBeNull();
    expect(AuditLog::where('action', 'remove_initiated')->where('resource_id', 'acme-rm-ok')->exists())->toBeTrue();
});

it('operador digita slug incorreto → 422', function () {
    [, $customer] = makeRemovableCustomer('acme-rm-wrong');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->deleteJson('/api/customers/acme-rm-wrong', [
        'confirm_slug' => 'acme-rm-WRONG',
        'backup_first' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'confirm_slug_mismatch');
});

it('role=suporte → 403', function () {
    [, $customer] = makeRemovableCustomer('acme-rm-suporte');
    $suporte = Operator::factory()->create(['role' => 'suporte', 'status' => 'active']);

    $response = $this->actingAs($suporte)->deleteJson('/api/customers/acme-rm-suporte', [
        'confirm_slug' => 'acme-rm-suporte',
        'backup_first' => true,
    ]);

    $response->assertStatus(403);
});

it('customer já em status=removing → 409', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'acme-already-removing',
        'cluster_server_id' => $cluster->id,
        'domain' => 'a.example.com',
        'status' => 'removing',
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->deleteJson('/api/customers/acme-already-removing', [
        'confirm_slug' => 'acme-already-removing',
        'backup_first' => false,
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error', 'already_in_progress');
});

it('SSH retorna exit 4 (state_conflict) → 409', function () {
    [, $customer] = makeRemovableCustomer('acme-rm-conflict');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andThrow(
        new SshRemoteException('State conflict', 4, stateConflict: true)
    );
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->deleteJson('/api/customers/acme-rm-conflict', [
        'confirm_slug' => 'acme-rm-conflict',
        'backup_first' => true,
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error', 'state_conflict');
});

it('AuditLog tem severity=high no payload', function () {
    [, $customer] = makeRemovableCustomer('acme-rm-audit');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(sshRemoveSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $sshMock);

    $this->actingAs($operator)->deleteJson('/api/customers/acme-rm-audit', [
        'confirm_slug' => 'acme-rm-audit',
        'backup_first' => true,
    ]);

    $log = AuditLog::where('action', 'remove_initiated')->where('resource_id', 'acme-rm-audit')->first();
    expect($log)->not->toBeNull();
    expect($log->payload['severity'])->toBe('high');
});

// D3-F009: sentinela de autorização cobre remoção via Livewire (Show component)
it('Livewire Show::remove() bloqueia suporte com 403 (D3-F009)', function () {
    [, $customer] = makeRemovableCustomer('acme-livewire-remove-gate');
    $suporte = Operator::factory()->create(['role' => 'suporte', 'status' => 'active']);

    Livewire::actingAs($suporte)
        ->test(Show::class, ['slug' => 'acme-livewire-remove-gate'])
        ->set('confirmInput', 'acme-livewire-remove-gate')
        ->call('remove')
        ->assertForbidden();
});

it('Livewire Show::remove() permite operador com gate provision-customers (D3-F009)', function () {
    [, $customer] = makeRemovableCustomer('acme-livewire-remove-ok');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(sshRemoveSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $sshMock);

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => 'acme-livewire-remove-ok'])
        ->set('confirmInput', 'acme-livewire-remove-ok')
        ->set('backupFirst', true)
        ->call('remove')
        ->assertHasNoErrors();

    expect(Customer::where('slug', 'acme-livewire-remove-ok')->value('status'))->toBe('removing');
    expect(Job::where('job_id', $jobId)->exists())->toBeTrue();
});
