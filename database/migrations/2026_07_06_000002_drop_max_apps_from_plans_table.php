<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plans', 'max_apps')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn('max_apps');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plans', 'max_apps')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->unsignedInteger('max_apps')->nullable()->after('max_users');
            });
        }
    }
};
