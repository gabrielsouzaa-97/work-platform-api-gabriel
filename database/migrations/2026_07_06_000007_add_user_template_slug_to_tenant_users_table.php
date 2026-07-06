<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->string('user_template_slug', 64)->nullable()->after('origin');
            $table->index('user_template_slug', 'idx_tenant_users_user_template_slug');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->dropIndex('idx_tenant_users_user_template_slug');
            $table->dropColumn('user_template_slug');
        });
    }
};
