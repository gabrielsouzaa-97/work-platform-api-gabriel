<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table): void {
            $table->string('invite_token_hash')->nullable()->after('status');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token_hash');
            $table->index('invite_expires_at', 'idx_operators_invite_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table): void {
            $table->dropIndex('idx_operators_invite_expires_at');
            $table->dropColumn(['invite_token_hash', 'invite_expires_at']);
        });
    }
};
