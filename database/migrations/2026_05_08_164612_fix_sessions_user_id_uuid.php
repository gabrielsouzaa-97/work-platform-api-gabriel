<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->index()->after('id');
        });
    }
};
