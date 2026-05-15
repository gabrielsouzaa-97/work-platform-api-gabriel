<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClient;
use App\Modules\Core\Ssh\SshConnectionPool;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use phpseclib3\Net\SSH2;

function makeActiveCluster(array $attrs = []): ClusterServer
{
    return ClusterServer::factory()->make(array_merge([
        'id' => '11111111-1111-1111-1111-111111111111',
        'status' => 'active',
        'ssh_host' => '10.0.0.1',
        'ssh_port' => 22,
        'ssh_user' => 'ncsaas-api',
        'ssh_private_key_encrypted' => '-----BEGIN RSA PRIVATE KEY-----\ntest\n-----END RSA PRIVATE KEY-----',
    ], $attrs));
}

function makeSshMock(string $stdout, string $stderr, int $exitCode): MockInterface
{
    $ssh = Mockery::mock(SSH2::class);
    $ssh->allows('setTimeout');
    $ssh->allows('exec')->andReturn($stdout);
    $ssh->allows('getLastError')->andReturn($stderr ?: null);
    $ssh->allows('getExitStatus')->andReturn($exitCode);

    return $ssh;
}

function makeSshClient(MockInterface $sshMock): SshClient
{
    $pool = Mockery::mock(SshConnectionPool::class);
    $pool->allows('get')->andReturn($sshMock);
    $pool->allows('remove');

    return new SshClient($pool);
}

it('returns parsed SshResponse for successful command', function (): void {
    $ssh = makeSshMock("hello\n", '', 0);
    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();

    $response = $client->run($cluster, 'echo', ['hello']);

    expect($response)->toBeInstanceOf(SshResponse::class)
        ->and($response->stdout)->toBe("hello\n")
        ->and($response->stderr)->toBe('')
        ->and($response->exitCode)->toBe(0);
});

it('parses JSON stdout into parsedJson field', function (): void {
    $payload = json_encode(['job_id' => 'abc-123', 'status' => 'queued']);
    $ssh = makeSshMock((string) $payload, '', 0);
    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();

    $response = $client->run($cluster, 'manage.sh', ['create', '--async', '--json']);

    expect($response->parsedJson)->toBeArray()
        ->and($response->parsedJson['job_id'])->toBe('abc-123');
});

it('throws SshTimeoutException for exit code 124', function (): void {
    $ssh = makeSshMock('', '', 124);
    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();

    expect(fn () => $client->run($cluster, 'manage.sh', ['slow-op']))
        ->toThrow(SshTimeoutException::class);
});

it('throws SshRemoteException with idempotencyConflict flag for exit code 3', function (): void {
    $ssh = makeSshMock('', 'idempotency conflict', 3);
    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();

    try {
        $client->run($cluster, 'manage.sh', ['create']);
        $this->fail('Expected SshRemoteException');
    } catch (SshRemoteException $e) {
        expect($e->idempotencyConflict)->toBeTrue()
            ->and($e->retryable)->toBeFalse()
            ->and($e->stateConflict)->toBeFalse();
    }
});

it('retries on connection failure and succeeds on second attempt', function (): void {
    $callCount = 0;
    $pool = Mockery::mock(SshConnectionPool::class);
    $pool->allows('remove');

    $pool->allows('get')->andReturnUsing(function () use (&$callCount): SSH2 {
        $callCount++;
        $ssh = Mockery::mock(SSH2::class);
        $ssh->allows('setTimeout');
        $ssh->allows('getLastError')->andReturn($callCount === 1 ? 'Connection refused' : null);
        $ssh->allows('getExitStatus')->andReturn(0);

        if ($callCount === 1) {
            $ssh->allows('exec')->andReturn(false);
        } else {
            $ssh->allows('exec')->andReturn("ok\n");
        }

        return $ssh;
    });

    $client = new SshClient($pool);
    $cluster = makeActiveCluster();

    $response = $client->run($cluster, 'echo', ['ok']);

    expect($response->exitCode)->toBe(0)
        ->and($callCount)->toBe(2);
});

it('does not log payloadStdin content', function (): void {
    $loggedContext = [];
    $ssh = makeSshMock("response\n", '', 0);
    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();

    Log::shouldReceive('channel')
        ->with('sshclient')
        ->andReturnSelf();

    Log::shouldReceive('debug')
        ->withArgs(function (string $msg, array $context) use (&$loggedContext): bool {
            $loggedContext = $context;

            return true;
        });

    $client->run($cluster, 'manage.sh', ['create'], 'secret_payload=password123');

    foreach ($loggedContext as $key => $value) {
        expect((string) $value)->not->toContain('password123');
    }
});

it('throws SshConnectionException for inactive cluster', function (): void {
    $pool = Mockery::mock(SshConnectionPool::class);
    $client = new SshClient($pool);
    $cluster = makeActiveCluster(['status' => 'inactive']);

    expect(fn () => $client->run($cluster, 'echo', ['test']))
        ->toThrow(SshConnectionException::class, 'not active');
});

it('throws SshRemoteException with retryable flag for exit code 2', function (): void {
    $ssh = makeSshMock('', 'queue_unavailable', 2);
    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();

    try {
        $client->run($cluster, 'manage.sh', ['create']);
        $this->fail('Expected SshRemoteException');
    } catch (SshRemoteException $e) {
        expect($e->retryable)->toBeTrue()
            ->and($e->remoteExitCode)->toBe(2);
    }
});

it('throws SshRemoteException with stateConflict flag for exit code 4', function (): void {
    $ssh = makeSshMock('', 'state_conflict', 4);
    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();

    try {
        $client->run($cluster, 'manage.sh', ['create']);
        $this->fail('Expected SshRemoteException');
    } catch (SshRemoteException $e) {
        expect($e->stateConflict)->toBeTrue()
            ->and($e->remoteExitCode)->toBe(4);
    }
});

it('payloadStdin is piped into exec command — not written after exec', function (): void {
    $capturedCmd = null;

    $ssh = Mockery::mock(SSH2::class);
    $ssh->allows('setTimeout');
    $ssh->allows('exec')->andReturnUsing(function (string $cmd) use (&$capturedCmd): string {
        $capturedCmd = $cmd;

        return '{"job_id":"x"}';
    });
    $ssh->allows('getLastError')->andReturn(null);
    $ssh->allows('getExitStatus')->andReturn(0);
    $ssh->shouldNotReceive('write');

    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();
    $payload = '{"slug":"my-customer","domain":"nc.example.com"}';

    $client->run($cluster, 'manage.sh', ['create', '--async', '--json'], $payload);

    expect($capturedCmd)
        ->toContain('printf %s')
        ->toContain(escapeshellarg($payload))
        ->toContain('| manage.sh')
        ->not->toContain("'manage.sh'");
});

it('payloadStdin null does not add printf pipe to exec command', function (): void {
    $capturedCmd = null;

    $ssh = Mockery::mock(SSH2::class);
    $ssh->allows('setTimeout');
    $ssh->allows('exec')->andReturnUsing(function (string $cmd) use (&$capturedCmd): string {
        $capturedCmd = $cmd;

        return '';
    });
    $ssh->allows('getLastError')->andReturn(null);
    $ssh->allows('getExitStatus')->andReturn(0);

    $client = makeSshClient($ssh);
    $cluster = makeActiveCluster();

    $client->run($cluster, 'manage.sh', ['list', '--json']);

    expect($capturedCmd)
        ->not->toContain('printf')
        ->toStartWith('manage.sh')
        ->not->toContain("'manage.sh'");
});
