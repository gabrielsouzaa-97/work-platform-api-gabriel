<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_commands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_agent_id');
            $table->uuid('operation_id')->unique();
            $table->string('operation', 128)->notNullable();
            $table->json('payload')->nullable();
            $table->string('idempotency_key', 36)->nullable();
            $table->string('status', 32)->notNullable()->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();

            $table->foreign('farm_agent_id')
                ->references('id')
                ->on('farm_agents')
                ->cascadeOnDelete();

            $table->index(['farm_agent_id', 'status'], 'idx_agent_commands_farm_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commands');
    }
};
