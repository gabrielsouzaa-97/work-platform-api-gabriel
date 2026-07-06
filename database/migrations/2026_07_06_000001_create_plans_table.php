<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->string('slug', 64)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('default_quota', 64);
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_apps')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status', 'idx_plans_status');
            $table->index('is_default', 'idx_plans_is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
