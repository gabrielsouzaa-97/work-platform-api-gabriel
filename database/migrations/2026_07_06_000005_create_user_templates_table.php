<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_templates', function (Blueprint $table): void {
            $table->string('slug', 64)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('default_quota', 64)->nullable();
            $table->json('groups');
            $table->json('permissions');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status', 'idx_user_templates_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_templates');
    }
};
