<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('operators')
                ->cascadeOnDelete();
        });

        Schema::table('operators', function (Blueprint $table): void {
            $table->unique('invite_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('operators', function (Blueprint $table): void {
            $table->dropUnique(['invite_token_hash']);
        });
    }
};
