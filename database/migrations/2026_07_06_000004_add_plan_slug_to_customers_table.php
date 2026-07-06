<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('plan_slug', 64)->nullable()->after('tier');

            $table->foreign('plan_slug')
                ->references('slug')
                ->on('plans')
                ->onDelete('set null');

            $table->index('plan_slug', 'idx_customers_plan_slug');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['plan_slug']);
            $table->dropIndex('idx_customers_plan_slug');
            $table->dropColumn('plan_slug');
        });
    }
};
