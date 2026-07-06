<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_apps', function (Blueprint $table): void {
            $table->string('plan_slug', 64);
            $table->uuid('app_catalog_id');

            $table->primary(['plan_slug', 'app_catalog_id']);

            $table->foreign('plan_slug')
                ->references('slug')
                ->on('plans')
                ->onDelete('cascade');

            $table->foreign('app_catalog_id')
                ->references('id')
                ->on('app_catalog_entries')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_apps');
    }
};
