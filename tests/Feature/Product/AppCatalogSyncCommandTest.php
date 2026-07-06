<?php

declare(strict_types=1);

use App\Models\AppCatalogEntry;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config([
        'platform.suite_catalog.path' => base_path('tests/fixtures/suite_catalog.json'),
    ]);
});

function activeFixtureAppIds(): array
{
    return ['dashboard', 'mail', 'spreed', 'deck'];
}

it('app-catalog:sync imports active apps from suite_catalog.json fixture', function (): void {
    $this->artisan('app-catalog:sync')->assertSuccessful();

    foreach (activeFixtureAppIds() as $appId) {
        $this->assertDatabaseHas('app_catalog_entries', [
            'app_id' => $appId,
            'is_active' => true,
        ]);
    }
});

it('app-catalog:sync marks planned upstream apps as inactive', function (): void {
    $this->artisan('app-catalog:sync')->assertSuccessful();

    $this->assertDatabaseHas('app_catalog_entries', [
        'app_id' => 'richdocuments',
        'is_active' => false,
    ]);
});

it('app-catalog:sync is idempotent on repeated runs', function (): void {
    $this->artisan('app-catalog:sync')->assertSuccessful();
    $firstCount = AppCatalogEntry::count();

    $this->artisan('app-catalog:sync')->assertSuccessful();

    expect(AppCatalogEntry::count())->toBe($firstCount);
    expect(AppCatalogEntry::where('app_id', 'mail')->count())->toBe(1);
});

it('app-catalog:sync reads path from platform.suite_catalog.path config', function (): void {
    $customPath = base_path('tests/fixtures/suite_catalog.json');
    config(['platform.suite_catalog.path' => $customPath]);

    Artisan::call('app-catalog:sync');

    expect(AppCatalogEntry::whereIn('app_id', activeFixtureAppIds())->count())
        ->toBe(count(activeFixtureAppIds()));
});

it('app-catalog:sync updates existing rows without duplicating app_id', function (): void {
    AppCatalogEntry::create([
        'app_id' => 'mail',
        'label' => 'Legacy Mail',
        'is_active' => false,
    ]);

    $this->artisan('app-catalog:sync')->assertSuccessful();

    expect(AppCatalogEntry::where('app_id', 'mail')->count())->toBe(1);
    expect(AppCatalogEntry::where('app_id', 'mail')->value('is_active'))->toBeTrue();
});
