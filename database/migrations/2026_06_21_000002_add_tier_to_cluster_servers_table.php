<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cluster_servers', function (Blueprint $table): void {
            $table->string('tier', 20)->default('shared')->after('status');
            $table->index('tier', 'idx_cluster_servers_tier');
        });
    }

    public function down(): void
    {
        Schema::table('cluster_servers', function (Blueprint $table): void {
            $table->dropIndex('idx_cluster_servers_tier');
            $table->dropColumn('tier');
        });
    }
};
