<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('objectstore_enabled')->default(false)->after('image_mode');
            $table->string('objectstore_bucket', 64)->nullable()->after('objectstore_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['objectstore_enabled', 'objectstore_bucket']);
        });
    }
};
