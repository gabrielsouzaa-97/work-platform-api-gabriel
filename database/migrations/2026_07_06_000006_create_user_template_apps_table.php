<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_template_apps', function (Blueprint $table): void {
            $table->string('user_template_slug', 64);
            $table->uuid('app_catalog_id');

            $table->primary(['user_template_slug', 'app_catalog_id'], 'pk_user_template_apps');

            $table->foreign('user_template_slug')
                ->references('slug')
                ->on('user_templates')
                ->onDelete('cascade');

            $table->foreign('app_catalog_id')
                ->references('id')
                ->on('app_catalog_entries')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_template_apps');
    }
};
