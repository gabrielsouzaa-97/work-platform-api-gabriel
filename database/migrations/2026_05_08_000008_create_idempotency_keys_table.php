<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->uuid('key')->primary();
            $table->string('cmd', 100)->notNullable();
            $table->string('args_hash', 255)->notNullable();
            $table->string('customer_slug', 64)->nullable();
            $table->uuid('job_id')->nullable();
            $table->timestamp('expires_at')->notNullable();
            $table->timestamps();

            $table->foreign('customer_slug')
                ->references('slug')
                ->on('customers');

            $table->foreign('job_id')
                ->references('job_id')
                ->on('jobs');

            $table->index('customer_slug', 'idx_idempotency_keys_customer_slug');
            $table->index('job_id', 'idx_idempotency_keys_job_id');
            $table->index('expires_at', 'idx_idempotency_keys_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
