<?php

declare(strict_types=1);

use App\Modules\Integration\Adapters\AgentPlatformAdapter;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Contracts\PlatformPort;

/**
 * @return list<string>
 */
function extendedPlatformPortMethods(): array
{
    return [
        'fetchJobLogs',
        'cancelJob',
        'pollJobStatus',
        'probeClusterHealth',
        'syncTenant',
        'runOccPassthrough',
        'dispatchManageAsync',
    ];
}

/**
 * @param  class-string  $adapterClass
 */
function assertAdapterImplementsExtendedPlatformPort(string $adapterClass): void
{
    $reflection = new ReflectionClass($adapterClass);

    expect($reflection->implementsInterface(PlatformPort::class))->toBeTrue();

    foreach (extendedPlatformPortMethods() as $methodName) {
        expect($reflection->hasMethod($methodName))
            ->toBeTrue("{$adapterClass} must implement {$methodName}()");

        $method = $reflection->getMethod($methodName);

        expect($method->isPublic())->toBeTrue();
        expect($method->getDeclaringClass()->getName())->toBe($adapterClass);
    }
}

it('SshPlatformAdapter implements extended PlatformPort contract methods', function (): void {
    assertAdapterImplementsExtendedPlatformPort(SshPlatformAdapter::class);
});

it('AgentPlatformAdapter implements extended PlatformPort contract methods', function (): void {
    assertAdapterImplementsExtendedPlatformPort(AgentPlatformAdapter::class);
});
