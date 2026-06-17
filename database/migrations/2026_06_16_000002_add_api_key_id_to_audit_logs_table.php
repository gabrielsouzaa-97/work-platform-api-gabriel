<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('api_key_id')->nullable()->after('actor_id');

            $table->foreign('api_key_id')
                ->references('id')
                ->on('api_keys');

            $table->index('api_key_id', 'idx_audit_logs_api_key_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['api_key_id']);
            $table->dropIndex('idx_audit_logs_api_key_id');
            $table->dropColumn('api_key_id');
        });
    }
};
