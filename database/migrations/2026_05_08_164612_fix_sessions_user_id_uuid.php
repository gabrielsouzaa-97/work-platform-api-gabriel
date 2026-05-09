<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BIGINT → UUID: incompatible types; all existing sessions are invalidated
     * on migration. Operators must re-login after deploy. The active.operator
     * middleware ensures any stale sessions are blocked on the next request.
     */
    public function up(): void
    {
        // Wipe sessions so deactivate() can reliably clean up by user_id post-migration.
        DB::table('sessions')->truncate();

        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        DB::table('sessions')->truncate();

        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->index()->after('id');
        });
    }
};
