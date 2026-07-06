<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('plans table does not have max_apps column', function (): void {
    expect(Schema::hasColumn('plans', 'max_apps'))->toBeFalse();
});

it('migration drop_max_apps_from_plans_table rolls back cleanly', function (): void {
    $migration = include database_path('migrations/2026_07_06_000002_drop_max_apps_from_plans_table.php');

    $migration->down();
    $migration->up();

    expect(Schema::hasColumn('plans', 'max_apps'))->toBeFalse();
});
