<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_inventories', function (Blueprint $table): void {
            $table->id();
            $table->string('farm_id', 64)->unique();
            $table->unsignedInteger('active_tenants');
            $table->unsignedInteger('max_tenants');
            $table->unsignedInteger('available_slots');
            $table->string('platform_version', 64);
            $table->unsignedInteger('latency_ms');
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index('reported_at', 'idx_farm_inventories_reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_inventories');
    }
};
