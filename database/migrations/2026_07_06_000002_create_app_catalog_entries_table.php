<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_catalog_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('app_id', 100)->unique();
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->string('category', 64)->nullable();
            $table->uuid('cluster_server_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('cluster_server_id')
                ->references('id')
                ->on('cluster_servers')
                ->onDelete('set null');

            $table->index('cluster_server_id', 'idx_app_catalog_cluster');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_catalog_entries');
    }
};
