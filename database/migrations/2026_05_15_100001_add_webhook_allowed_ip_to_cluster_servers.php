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
            $table->string('webhook_allowed_ip', 45)->nullable()->after('webhook_secret_version');
        });
    }

    public function down(): void
    {
        Schema::table('cluster_servers', function (Blueprint $table): void {
            $table->dropColumn('webhook_allowed_ip');
        });
    }
};
