<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
    }

    public function down(): void
    {
        // Extension is kept even on rollback — other migrations depend on it.
    }
};
