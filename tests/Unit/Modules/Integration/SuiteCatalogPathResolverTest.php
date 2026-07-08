<?php

declare(strict_types=1);

use App\Modules\Integration\Support\SuiteCatalogPathResolver;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempDir = storage_path('app/f24-suite-catalog-'.uniqid());
    File::makeDirectory($this->tempDir, 0755, true);
});

afterEach(function (): void {
    if (isset($this->tempDir) && File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

function suiteCatalogFixturePath(string $dir, string $filename = 'suite_catalog.json'): string
{
    $path = $dir.DIRECTORY_SEPARATOR.$filename;
    File::put($path, json_encode(['version' => 1, 'apps' => []], JSON_THROW_ON_ERROR));

    return $path;
}

it('resolve returns first readable configured path (CQ-F17-004)', function (): void {
    $readable = suiteCatalogFixturePath($this->tempDir, 'primary.json');
    config(['platform.suite_catalog.path' => $readable]);

    expect(app(SuiteCatalogPathResolver::class)->resolve())->toBe($readable);
});

it('resolve falls through when primary path is missing (CQ-F17-004)', function (): void {
    config(['platform.suite_catalog.path' => $this->tempDir.DIRECTORY_SEPARATOR.'does-not-exist.json']);

    expect(app(SuiteCatalogPathResolver::class)->resolve())
        ->toBe(storage_path('app/suite_catalog.json'));
});

it('resolve throws RuntimeException when every candidate path is unreadable (CQ-F17-004)', function (): void {
    config(['platform.suite_catalog.path' => $this->tempDir.DIRECTORY_SEPARATOR.'missing-primary.json']);

    $bundled = storage_path('app/suite_catalog.json');
    $bundledBackup = $bundled.'.f24-backup';

    expect(File::exists($bundled))->toBeTrue("Bundled catalog required for negative-path setup: {$bundled}");

    File::move($bundled, $bundledBackup);

    try {
        expect(fn () => app(SuiteCatalogPathResolver::class)->resolve())
            ->toThrow(RuntimeException::class);
    } finally {
        File::move($bundledBackup, $bundled);
    }
});
