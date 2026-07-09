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
            $table->boolean('legacy_me360_capable')
                ->default(false)
                ->after('tier');
        });
    }

    public function down(): void
    {
        Schema::table('cluster_servers', function (Blueprint $table): void {
            $table->dropColumn('legacy_me360_capable');
        });
    }
};
