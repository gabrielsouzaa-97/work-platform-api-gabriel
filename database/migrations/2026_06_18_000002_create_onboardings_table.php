<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboardings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_slug', 64)->notNullable();
            $table->uuid('correlation_id')->notNullable();
            $table->string('state', 32)->notNullable()->default('pending');
            $table->string('current_step', 64)->nullable();
            $table->json('steps')->nullable();
            $table->string('idempotency_key', 64)->notNullable();
            $table->uuid('api_key_id')->nullable();
            $table->timestamps();

            $table->foreign('api_key_id')
                ->references('id')
                ->on('api_keys');

            $table->unique('idempotency_key', 'idx_onboardings_idempotency_key');
            $table->index('tenant_slug', 'idx_onboardings_tenant_slug');
            $table->index('correlation_id', 'idx_onboardings_correlation_id');
            $table->index('state', 'idx_onboardings_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboardings');
    }
};
