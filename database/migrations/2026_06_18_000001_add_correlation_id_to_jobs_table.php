<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->uuid('correlation_id')->nullable()->after('idempotency_key');
            $table->index('correlation_id', 'idx_jobs_correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropIndex('idx_jobs_correlation_id');
            $table->dropColumn('correlation_id');
        });
    }
};
