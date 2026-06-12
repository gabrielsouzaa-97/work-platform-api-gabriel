<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_agents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('farm_id', 64)->unique();
            $table->uuid('cluster_server_id')->nullable()->unique();
            $table->string('agent_token_hash', 64)->notNullable();
            $table->string('mtls_cert_fingerprint', 128)->nullable();
            $table->string('status', 32)->notNullable()->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cluster_server_id')
                ->references('id')
                ->on('cluster_servers')
                ->nullOnDelete();

            $table->index('status', 'idx_farm_agents_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_agents');
    }
};
