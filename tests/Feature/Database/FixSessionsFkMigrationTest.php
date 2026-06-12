<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('migration adds sessions FK to operators and unique invite_token_hash', function () {
    expect(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::hasTable('operators'))->toBeTrue();

    $sessionsForeignKeys = collect(Schema::getConnection()->getSchemaBuilder()->getForeignKeys('sessions'))
        ->filter(fn (array $fk): bool => in_array('user_id', $fk['columns'], true));

    expect($sessionsForeignKeys)->not->toBeEmpty();
    $fk = $sessionsForeignKeys->first();
    expect($fk['foreign_table'])->toBe('operators')
        ->and($fk['foreign_columns'])->toBe(['id'])
        ->and($fk['on_delete'])->toBe('cascade');

    $operatorIndexes = collect(Schema::getConnection()->getSchemaBuilder()->getIndexes('operators'))
        ->filter(fn (array $index): bool => $index['unique'] === true
            && in_array('invite_token_hash', $index['columns'], true));

    expect($operatorIndexes)->not->toBeEmpty();
});

it('migration fix_sessions_fk_and_operator_unique rolls back cleanly', function () {
    $migration = include database_path('migrations/2026_06_12_000001_fix_sessions_fk_and_operator_unique.php');

    $migration->down();
    $migration->up();

    expect(true)->toBeTrue();
});
