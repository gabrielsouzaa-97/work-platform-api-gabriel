<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table): void {
            $table->uuid('job_id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('customer_slug', 64)->notNullable();
            $table->uuid('cluster_server_id')->notNullable();
            $table->string('cmd_canonical', 100)->notNullable();
            $table->string('job_type', 100)->notNullable();
            $table->string('state', 50)->notNullable()->default('queued');
            $table->uuid('idempotency_key')->unique()->notNullable();
            $table->jsonb('payload_sanitized')->nullable();
            $table->jsonb('summary')->nullable();
            $table->integer('exit_code')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('callback_received_at')->nullable();
            $table->timestamp('last_poll_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_slug')
                ->references('slug')
                ->on('customers');

            $table->foreign('cluster_server_id')
                ->references('id')
                ->on('cluster_servers');

            $table->index('customer_slug', 'idx_jobs_customer_slug');
            $table->index('cluster_server_id', 'idx_jobs_cluster_server_id');
            $table->index('state', 'idx_jobs_state');
            $table->index('job_type', 'idx_jobs_job_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
