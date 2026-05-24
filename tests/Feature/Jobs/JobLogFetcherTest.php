<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Jobs\Services\JobLogFetcher;
use App\Modules\Jobs\Services\WebhookHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// ── Helpers ────────────────────────────────────────────────────────────────────

function fetcherCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function fetcherJob(string $clusterId, ?string $customerSlug = 'fetcher-acme', ?array $summary = null): Job
{
    if ($customerSlug !== null) {
        Customer::firstOrCreate(['slug' => $customerSlug], [
            'cluster_server_id' => $clusterId,
            'domain' => "{$customerSlug}.example.com",
            'status' => 'active',
        ]);
    }

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $customerSlug,
        'cluster_server_id' => $clusterId,
        'cmd_canonical' => 'nextcloud-manage fetcher-acme _ user_create',
        'job_type' => 'user_create',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(3),
        'summary' => $summary,
    ]);
}

function sshLogsResponse(array $lines): SshResponse
{
    return new SshResponse(
        stdout: json_encode($lines),
        stderr: '',
        exitCode: 0,
        parsedJson: $lines,
    );
}

function sshPlainTextLogsResponse(string $stdout): SshResponse
{
    return new SshResponse(
        stdout: $stdout,
        stderr: '',
        exitCode: 0,
    );
}

function sshNotImplementedResponse(): SshResponse
{
    return new SshResponse(stdout: '', stderr: 'not implemented', exitCode: 99);
}

function sshStatusWithSummaryResponse(array $summaryLines): SshResponse
{
    $data = ['data' => ['summary' => $summaryLines]];

    return new SshResponse(
        stdout: json_encode($data),
        stderr: '',
        exitCode: 0,
        parsedJson: $data,
    );
}

function isJobIntrospectionArgs(array $args, string $jobId, string $subcommand): bool
{
    return $args === ['job', $jobId, $subcommand, '--json'];
}

// ── JobLogFetcher unit tests ───────────────────────────────────────────────────

it('JobLogFetcher: retorna linhas do SSH quando logs --json responde com exit 0', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && isJobIntrospectionArgs($args, $job->job_id, 'logs'))
        ->andReturn(sshLogsResponse(['Line 1', 'Line 2']));
    $this->app->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines)->toBe(['Line 1', 'Line 2']);
});

it('JobLogFetcher: quando exit_code=99 faz fallback para job status --json e extrai summary', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => isJobIntrospectionArgs($args, $job->job_id, 'logs'))
        ->andReturn(sshNotImplementedResponse());
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => isJobIntrospectionArgs($args, $job->job_id, 'status'))
        ->andReturn(sshStatusWithSummaryResponse(['Status line 1', 'Status line 2']));
    $this->app->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines)->toBe(['Status line 1', 'Status line 2']);
});

it('JobLogFetcher: SshRemoteException notImplemented dispara fallback para status --json', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => isJobIntrospectionArgs($args, $job->job_id, 'logs'))
        ->andThrow(new SshRemoteException('not implemented', remoteExitCode: 99, notImplemented: true));
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => isJobIntrospectionArgs($args, $job->job_id, 'status'))
        ->andReturn(sshStatusWithSummaryResponse(['Exception fallback line']));
    $this->app->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines)->toBe(['Exception fallback line']);
});

it('JobLogFetcher: sanitiza password=foo para password=[REDACTED] antes de persistir', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andReturn(sshLogsResponse([
            'Configuring user john password=segredo123',
            'token=abc123xyz set for session',
            'Normal log line',
        ]));
    $this->app->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines[0])->toContain('[REDACTED]')
        ->and($lines[0])->not->toContain('segredo123')
        ->and($lines[1])->toContain('[REDACTED]')
        ->and($lines[1])->not->toContain('abc123xyz')
        ->and($lines[2])->toBe('Normal log line');
});

it('JobLogFetcher: extrai linhas de JSON upstream com stdout/stderr (formato occ-exec)', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $payload = [
        'schema_version' => '1',
        'occ_command' => 'user:delete',
        'exit_code' => 0,
        'stdout' => "The specified user was deleted\nDone.",
        'stderr' => '',
    ];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => isJobIntrospectionArgs($args, $job->job_id, 'logs'))
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));
    $this->app->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines)->toBe(['The specified user was deleted', 'Done.']);
});

it('JobLogFetcher: aceita stdout em texto puro no comando logs e separa por linhas', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andReturn(sshPlainTextLogsResponse(implode(PHP_EOL, [
            'Line 1',
            'token=super-secret',
            '',
            'Line 3',
        ])));
    $this->app->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines)->toBe([
        'Line 1',
        'token=[REDACTED]',
        'Line 3',
    ]);
});

it('JobLogFetcher: retorna [] quando job já tem summary preenchido (idempotência)', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id, summary: ['existing line']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines)->toBe([]);
});

// ── WebhookHandler + JobLogFetcher integration tests ──────────────────────────

it('webhook job.finished popula summary com linhas retornadas pelo fetcher', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andReturn(sshLogsResponse(['Log line A', 'Log line B']));
    $this->app->instance(SshClientInterface::class, $ssh);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);

    $job->refresh();
    expect($job->state)->toBe('success')
        ->and($job->summary)->toBe(['Log line A', 'Log line B']);
});

it('webhook job.finished com summary já preenchida: fetcher não é chamado (idempotência)', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id, summary: ['pre-existing line']);
    $job->update(['state' => 'running']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);

    $job->refresh();
    expect($job->summary)->toBe(['pre-existing line']);
});

it('webhook job.finished tolera falha do fetcher: estado persistido, summary null, Log::warning emitido', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->andThrow(new SshConnectionException('SSH unreachable'));
    $this->app->instance(SshClientInterface::class, $ssh);

    $logged = false;
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($key) => $key === 'jobs.log_fetch.failed')
        ->andReturnUsing(function () use (&$logged) {
            $logged = true;
        });
    Log::shouldReceive('info')->zeroOrMoreTimes();

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);

    $job->refresh();
    expect($job->state)->toBe('success')
        ->and($job->summary)->toBeNull()
        ->and($logged)->toBeTrue();
});

it('webhook job.finished retry com mesmo estado recupera summary quando primeiro fetch falhou', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andThrow(new SshConnectionException('SSH unreachable'));
    $ssh->shouldReceive('run')
        ->once()
        ->andReturn(sshLogsResponse(['Recovered line']));
    $this->app->instance(SshClientInterface::class, $ssh);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($key) => $key === 'jobs.log_fetch.failed');
    Log::shouldReceive('info')->zeroOrMoreTimes();

    $payload = [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ];

    app(WebhookHandler::class)->handle($cluster, $payload);
    $job->refresh();
    expect($job->state)->toBe('success')
        ->and($job->summary)->toBeNull();

    app(WebhookHandler::class)->handle($cluster, $payload);
    $job->refresh();
    expect($job->summary)->toBe(['Recovered line']);
});

it('webhook job.started NÃO chama fetcher (apenas job.finished dispara pull)', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.started',
        'job_id' => $job->job_id,
        'state' => 'running',
        'started_at' => now()->toIso8601String(),
    ]);

    $job->refresh();
    expect($job->state)->toBe('running');
});

it('webhook job.finished com fetcher retornando [] mantém summary null', function (): void {
    $cluster = fetcherCluster();
    $job = fetcherJob($cluster->id);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => isJobIntrospectionArgs($args, $job->job_id, 'logs'))
        ->andReturn(sshLogsResponse([]));
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => isJobIntrospectionArgs($args, $job->job_id, 'status'))
        ->andReturn(sshStatusWithSummaryResponse([]));
    $this->app->instance(SshClientInterface::class, $ssh);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);

    $job->refresh();
    expect($job->summary)->toBeNull();
});
