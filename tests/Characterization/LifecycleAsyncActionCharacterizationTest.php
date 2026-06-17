<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

it('characterizes lifecycle async dispatches manage argv through SSH adapter', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'char-lifecycle-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-lifecycle.example.com',
        'status' => 'active',
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args, ?string $stdin) use ($customer): bool {
            return $cmd === 'nextcloud-manage'
                && $args[0] === $customer->slug
                && $args[1] === 'user'
                && $args[2] === 'create'
                && in_array('alice', $args, true)
                && str_contains((string) $stdin, 'Secret123!');
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $sshMock);

    $job = app(LifecycleAsyncAction::class)->execute(
        $customer,
        'users:create',
        ['alice'],
        ['password' => 'Secret123!', 'email' => 'alice@example.com'],
        $operator,
    );

    expect($job->job_id)->toBe($jobId);
    expect(AuditLog::where('action', 'users_create_initiated')->where('resource_id', $customer->slug)->exists())->toBeTrue();
});

it('characterizes lifecycle async still uses SSH client path through port adapter', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'char-lifecycle-ssh-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-lifecycle-ssh.example.com',
        'status' => 'active',
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args) use ($customer): bool {
            return $cmd === 'nextcloud-manage'
                && $args[0] === $customer->slug
                && $args[1] === 'apps'
                && $args[2] === 'enable';
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $sshMock);

    $job = app(LifecycleAsyncAction::class)->execute(
        $customer,
        'apps:enable',
        ['files,calendar'],
        null,
        $operator,
    );

    expect($job->job_id)->toBe($jobId);
});

it('characterizes lifecycle async rolls back idempotency key on connection failure', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'char-lifecycle-fail-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-lifecycle-fail.example.com',
        'status' => 'active',
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andThrow(new SshConnectionException('down'));
    app()->instance(SshClientInterface::class, $sshMock);

    expect(fn () => app(LifecycleAsyncAction::class)->execute(
        $customer,
        'users:delete',
        ['alice'],
        null,
        $operator,
    ))->toThrow(ClusterUnreachableException::class);

    expect(IdempotencyKey::where('customer_slug', $customer->slug)->where('cmd', 'users:delete')->exists())
        ->toBeFalse();
});
