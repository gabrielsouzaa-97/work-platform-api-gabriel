<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * MariaDB 11 has native UUID() function built-in — no extension needed.
 * Migration kept as no-op for compatibility with migration history.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MariaDB: UUID() is a built-in function, no extension required.
    }

    public function down(): void
    {
        // no-op
    }
};
