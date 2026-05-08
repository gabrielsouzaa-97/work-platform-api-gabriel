<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('actor_id')->notNullable();
            $table->string('action', 100)->notNullable();
            $table->string('resource_type', 100)->notNullable();
            $table->string('resource_id', 255)->notNullable();
            $table->jsonb('payload')->nullable();
            $table->uuid('cluster_server_id')->nullable();
            $table->uuid('job_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('actor_id')
                ->references('id')
                ->on('operators');

            $table->foreign('cluster_server_id')
                ->references('id')
                ->on('cluster_servers');

            $table->foreign('job_id')
                ->references('job_id')
                ->on('jobs');

            $table->index('actor_id', 'idx_audit_logs_actor_id');
            $table->index('action', 'idx_audit_logs_action');
            $table->index('resource_type', 'idx_audit_logs_resource_type');
            $table->index('cluster_server_id', 'idx_audit_logs_cluster_server_id');
            $table->index('job_id', 'idx_audit_logs_job_id');
            $table->index('created_at', 'idx_audit_logs_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
