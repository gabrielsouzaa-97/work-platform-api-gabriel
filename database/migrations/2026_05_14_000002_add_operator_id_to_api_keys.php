<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->uuid('operator_id')->nullable()->after('id');

            $table->foreign('operator_id')
                ->references('id')
                ->on('operators')
                ->nullOnDelete();

            $table->index('operator_id', 'idx_api_keys_operator_id');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->dropForeign('api_keys_operator_id_foreign');
            $table->dropIndex('idx_api_keys_operator_id');
            $table->dropColumn('operator_id');
        });
    }
};
