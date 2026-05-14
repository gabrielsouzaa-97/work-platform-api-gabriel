<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_secret_history', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('cluster_server_id')->notNullable();
            $table->text('secret_encrypted')->notNullable();
            $table->integer('version')->notNullable();
            $table->timestamp('valid_from')->notNullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->foreign('cluster_server_id')
                ->references('id')
                ->on('cluster_servers');

            $table->index('cluster_server_id', 'idx_webhook_secret_history_cluster_server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_secret_history');
    }
};
