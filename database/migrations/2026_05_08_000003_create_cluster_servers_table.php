<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_servers', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('name', 255)->notNullable();
            $table->string('ssh_host', 255)->notNullable();
            $table->integer('ssh_port')->notNullable()->default(22);
            $table->string('ssh_user', 100)->notNullable()->default('ncsaas-api');
            $table->text('ssh_private_key_encrypted')->notNullable();
            $table->text('webhook_secret_encrypted')->notNullable();
            $table->integer('webhook_secret_version')->notNullable()->default(1);
            $table->string('nextcloud_version', 50)->nullable();
            $table->integer('schema_version')->notNullable()->default(1);
            $table->string('status', 50)->notNullable()->default('active');
            $table->timestamp('last_health_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_cluster_servers_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_servers');
    }
};
