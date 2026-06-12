<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL: table defaults use UUID() (MySQL/MariaDB-compatible name).
 * MariaDB 11 exposes native UUID(); pgsql gets a shim over gen_random_uuid().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION uuid() RETURNS uuid
            LANGUAGE sql
            VOLATILE
            AS $$ SELECT gen_random_uuid() $$
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP FUNCTION IF EXISTS uuid()');
    }
};
