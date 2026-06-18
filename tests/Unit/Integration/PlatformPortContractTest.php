<?php

declare(strict_types=1);

use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\CancelJobCommand;
use App\Modules\Integration\Dto\CancelJobResult;
use App\Modules\Integration\Dto\ClusterHealthReport;
use App\Modules\Integration\Dto\FetchJobLogsCommand;
use App\Modules\Integration\Dto\JobLogsResult;
use App\Modules\Integration\Dto\JobStatusResult;
use App\Modules\Integration\Dto\OccPassthroughCommand;
use App\Modules\Integration\Dto\OccPassthroughOperation;
use App\Modules\Integration\Dto\OccPassthroughResult;
use App\Modules\Integration\Dto\PollJobStatusCommand;
use App\Modules\Integration\Dto\ProbeClusterHealthCommand;
use App\Modules\Integration\Dto\SyncTenantCommand;
use App\Modules\Integration\Dto\SyncTenantResult;

/**
 * @param  class-string  $parameterClass
 * @param  class-string  $returnClass
 */
function assertPlatformPortMethod(string $methodName, string $parameterClass, string $returnClass): void
{
    $reflection = new ReflectionClass(PlatformPort::class);

    expect($reflection->hasMethod($methodName))
        ->toBeTrue("PlatformPort must declare {$methodName}()");

    $method = $reflection->getMethod($methodName);
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getType()?->getName())->toBe($parameterClass);
    expect($method->getReturnType()?->getName())->toBe($returnClass);
}

it('PlatformPort declares fetchJobLogs with FetchJobLogsCommand returning JobLogsResult', function (): void {
    assertPlatformPortMethod('fetchJobLogs', FetchJobLogsCommand::class, JobLogsResult::class);
});

it('PlatformPort declares cancelJob with CancelJobCommand returning CancelJobResult', function (): void {
    assertPlatformPortMethod('cancelJob', CancelJobCommand::class, CancelJobResult::class);
});

it('PlatformPort declares pollJobStatus with PollJobStatusCommand returning JobStatusResult', function (): void {
    assertPlatformPortMethod('pollJobStatus', PollJobStatusCommand::class, JobStatusResult::class);
});

it('PlatformPort declares probeClusterHealth with ProbeClusterHealthCommand returning ClusterHealthReport', function (): void {
    assertPlatformPortMethod('probeClusterHealth', ProbeClusterHealthCommand::class, ClusterHealthReport::class);
});

it('PlatformPort declares syncTenant with SyncTenantCommand returning SyncTenantResult', function (): void {
    assertPlatformPortMethod('syncTenant', SyncTenantCommand::class, SyncTenantResult::class);
});

it('PlatformPort declares runOccPassthrough typed via OccPassthroughOperation enum', function (): void {
    assertPlatformPortMethod(
        'runOccPassthrough',
        OccPassthroughCommand::class,
        OccPassthroughResult::class,
    );

    expect(enum_exists(OccPassthroughOperation::class))->toBeTrue();

    $commandReflection = new ReflectionClass(OccPassthroughCommand::class);
    $constructor = $commandReflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $operationParam = collect($constructor->getParameters())
        ->first(fn (ReflectionParameter $parameter): bool => $parameter->getName() === 'operation');

    expect($operationParam)->not->toBeNull();
    expect($operationParam->getType()?->getName())->toBe(OccPassthroughOperation::class);
});

it('PlatformPort does not expose generic execOcc escape hatch', function (): void {
    $reflection = new ReflectionClass(PlatformPort::class);

    expect($reflection->hasMethod('execOcc'))->toBeFalse();
});
