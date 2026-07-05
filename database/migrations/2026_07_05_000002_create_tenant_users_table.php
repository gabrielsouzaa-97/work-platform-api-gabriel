<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('customer_slug', 64)->notNullable();
            $table->string('username', 64)->notNullable();
            $table->string('email', 255)->nullable();
            $table->string('quota', 64)->nullable();
            $table->json('groups')->nullable();
            $table->string('origin', 20)->notNullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_slug')
                ->references('slug')
                ->on('customers')
                ->onDelete('cascade');

            $table->unique(['customer_slug', 'username'], 'uniq_tenant_users_customer_username');
            $table->index('customer_slug', 'idx_tenant_users_customer_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
