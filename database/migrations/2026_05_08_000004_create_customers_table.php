<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->string('slug', 64)->primary();
            $table->uuid('cluster_server_id')->notNullable();
            $table->string('domain', 255)->notNullable();
            $table->string('status', 50)->notNullable()->default('provisioning');
            $table->jsonb('branding_meta')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cluster_server_id')
                ->references('id')
                ->on('cluster_servers')
                ->onDelete('restrict');

            $table->index('cluster_server_id', 'idx_customers_cluster_server_id');
            $table->index('status', 'idx_customers_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
