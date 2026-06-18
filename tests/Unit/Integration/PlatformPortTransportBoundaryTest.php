<?php

declare(strict_types=1);

it('PlatformPort contract does not reference transport exceptions', function (): void {
    $source = file_get_contents(
        dirname(__DIR__, 3).'/app/Modules/Integration/Contracts/PlatformPort.php',
    );

    expect($source)->not->toBeFalse();
    expect($source)->not->toContain('SshClientException');
    expect($source)->not->toContain('SshRemoteException');
    expect($source)->not->toContain('SshTimeoutException');
    expect($source)->toContain('UpstreamUnavailableException');
    expect($source)->toContain('CapabilityBlockedException');
});

it('Customers and Jobs modules do not import SshClientException', function (): void {
    $roots = [
        dirname(__DIR__, 3).'/app/Modules/Customers',
        dirname(__DIR__, 3).'/app/Modules/Jobs',
    ];

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            expect($contents)->not->toContain(
                'SshClientException',
                "unexpected SshClientException import in {$file->getPathname()}",
            );
        }
    }
});
