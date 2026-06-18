<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboardings', function (Blueprint $table): void {
            $table->text('admin_payload')->nullable()->after('api_key_id');
            $table->json('apps_enabled')->nullable()->after('admin_payload');
            $table->json('branding_fields')->nullable()->after('apps_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('onboardings', function (Blueprint $table): void {
            $table->dropColumn(['admin_payload', 'apps_enabled', 'branding_fields']);
        });
    }
};
